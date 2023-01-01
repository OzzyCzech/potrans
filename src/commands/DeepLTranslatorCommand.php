<?php

namespace potrans\commands;

use DeepL\Translator;
use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
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

class DeepLTranslatorCommand extends Command {

	protected function configure(): void {
		$this->addArgument('input', InputArgument::REQUIRED, 'Input PO file path')
			->addArgument('output', InputArgument::OPTIONAL, 'Output PO, MO files directory', '~/Downloads')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source language (default: en)', 'en')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target language (default: cs)', 'cs')
			->addOption('all', null, InputOption::VALUE_NONE, 'Re-translate including translated sentences')
			->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Wait between translations in milliseconds', false)
			->addOption('apikey', null, InputOption::VALUE_REQUIRED, 'Deepl API Key')
			->addOption('cache', null, InputOption::VALUE_NEGATABLE, 'Load from cache or not', true);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		// input PO file loading
		$wait = $input->getOption('wait');
		$cache = new FilesystemAdapter('deepl', 3600, 'cache');

		try {

			$inputFile = $input->getArgument('input');
			if (!is_file($inputFile)) {
				throw new RuntimeException(sprintf('Input file "%s" not found', $inputFile));
			}

			$poLoader = new PoLoader();
			$translations = $poLoader->loadFile($inputFile);

			// output directory
			$outputDir = realpath($input->getArgument('output')) . DIRECTORY_SEPARATOR;
			if (!is_dir($outputDir)) {
				throw new InvalidOptionException('Invalid directory path: ' . $outputDir);
			}

			$from = $input->getOption('from');
			$to = $input->getOption('to');
			$apikey = $input->getOption('apikey');

			$translator = new Translator($apikey);

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

						// TODO add Text translation options
						// @see https://github.com/DeepLcom/deepl-php#text-translation-options
						$response = $translator->translateText(
							$sentence->getOriginal(),
							$from,
							$to,
						);

						$translation->set($response->text); // set new translation

						// save only successful translations
						if ($response->text && $input->getOption('cache')) {
							$cache->save($translation);
						}
					}

					$sentence->translate($translation->get());

					// verbose mode show everything
					if ($output->isVeryVerbose()) {
						$output->writeln(
							[
								'-------------------------------------------------------------------------',
								' > ' . $sentence->getOriginal(),
								' > ' . $sentence->getTranslation(),
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

			// done!
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
		return 'Translate PO file with DeepL Translator API';
	}

}