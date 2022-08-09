<?php

namespace potrans\commands;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations;
use Google\Cloud\Translate\V3\TranslationServiceClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class GoogleTranslatorCommand extends Command {

	protected function configure(): void {
		$this->addArgument('input', InputArgument::REQUIRED, 'Input PO file path')
			->addArgument('output', InputArgument::OPTIONAL, 'Output PO, MO files directory', '~/Downloads')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source language (default: en)', 'en')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target language (default: cs)', 'cs')
			->addOption('all', null, InputOption::VALUE_NONE, 'Re-translate including translated sentences')
			->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Wait between translations in milliseconds', false)
			->addOption('credentials', null, InputOption::VALUE_REQUIRED, 'Path to Google Credentials file', './credentials.json')
			->addOption('project', null, InputOption::VALUE_REQUIRED, 'Google Cloud Project ID <comment>[default: project_id from credentials.json]</comment>')
			->addOption('location', null, InputOption::VALUE_REQUIRED, 'Google Cloud Location', 'global')
			->addOption('cache', null, InputOption::VALUE_NEGATABLE, 'Load from cache or not', true);
	}

	/**
	 * @param string $file
	 * @return Translations
	 */
	public function loadPoFile(string $file): Translations {
		if (!file_exists($file)) {
			throw new RuntimeException(sprintf('Input file "%s" not found', $file));
		}

		$po = new PoLoader();
		return $po->loadFile($file);
	}

	/**
	 * @throws \Google\ApiCore\ValidationException
	 * @throws \Google\ApiCore\ApiException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$cache = new FilesystemAdapter('google', 3600, 'cache');

		try {

			$from = $input->getOption('from');
			$to = $input->getOption('to');
			$inputFile = $input->getArgument('input');
			$wait = $input->getOption('wait');

			// load translations
			$translations = $this->loadPoFile($inputFile);

			// credentials
			$credentials = $input->getOption('credentials');
			if (!is_file($credentials)) {
				throw new InvalidOptionException('Missing credentials or API key');
			}
			$translator = new TranslationServiceClient(['credentials' => $credentials]);

			// get project from credentials
			$project = $input->getOption('project');
			if (!$project) {
				$project = json_decode(file_get_contents($credentials))->project_id;
			}

			// output directory
			$outputDir = realpath($input->getArgument('output')) . DIRECTORY_SEPARATOR;
			if (!is_dir($outputDir)) {
				throw new InvalidOptionException('Invalid directory path: ' . $outputDir);
			}

			// translator
			$output->writeln(
				[
					'------------------------------',
					' PO trans translator',
					'------------------------------',
					'<comment>Input</comment>: ' . $inputFile,
					'<comment>Translate</comment>: from ' . $from . ' to ' . $to,
					'<comment>Output dir</comment>: ' . $outputDir,
					'<comment>Translating progress</comment>:',
				]
			);

			$progress = new ProgressBar($output, count($translations));

			$translated = 0; // counter
			/** @var Translation $sentence */
			foreach ($translations as $sentence) {

				if (!$sentence->getTranslation() || $input->getOption('all')) {
					// translated counter
					$translated++;

					$key = md5($sentence->getOriginal() . $from . $to);
					$translation = $cache->getItem($key);

					if (!$translation->isHit() || !$input->getOption('cache')) {

						$response = $translator->translateText(
							[$sentence->getOriginal()],
							$to,
							TranslationServiceClient::locationName($project, $input->getOption('location')),
							['sourceLanguageCode' => $from]
						);

						$translation->set($response->getTranslations()[0]->getTranslatedText());

						// save to cache
						if ($input->getOption('cache')) {
							$cache->save($translation);
						}
					}

					$sentence->translate($translation->get());

					// verbose mode show everything
					if ($output->isVeryVerbose()) {
						$output->writeln(
							[
								'-------------------------------------------------------------------------',
								' > <info>' . $sentence->getOriginal() . '</info>',
								' > <comment>' . $sentence->getTranslation() . '</comment>',
							]
						);
					}
				}

				// progress
				if (!$output->isVeryVerbose()) {
					$progress->advance();
				}

				if ($wait) usleep($wait);
			}

			if (!$output->isVeryVerbose()) {
				$progress->finish();
				$output->writeln('');
			}

			$output->writeln('<comment>Translated :</comment> ' . $translated . ' sentences');

			// MO file output
			$moGenerator = new MoGenerator();
			$moOutputFile = $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.mo';
			if ($output->isVeryVerbose()) {
				$output->writeln('<comment>Writing new MO File</comment>: ' . $moOutputFile);
			}
			$moGenerator->generateFile($translations, $moOutputFile);

			// PO file output
			$poGenerator = new PoGenerator();
			$poOutputFile = $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.po';
			if ($output->isVeryVerbose()) {
				$output->writeln('<comment>Writing new PO File</comment>: ' . $poOutputFile);
			}
			$poGenerator->generateFile($translations, $poOutputFile);

			$output->writeln('<info>DONE!</info>');
		} catch (Throwable $error) {
			$output->writeln(
				[
					'',
					sprintf("<error>ERROR: %s on %s at %s</error>", $error->getMessage(), $error->getFile(), $error->getLine()),
				]
			);
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	public function getDescription(): string {
		return 'Translate PO file with Google Translator API';
	}

}