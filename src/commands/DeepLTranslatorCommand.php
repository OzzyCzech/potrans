<?php

namespace potrans\commands;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use GuzzleHttp\Client;
use potrans\commands\DeepLTranslator;
use potrans\commands\GoogleTranslator;
use potrans\commands\ITranslator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

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

					$translation = $cache->getItem(md5($sentence->getOriginal()));
					if (!$translation->isHit() || !$input->getOption('cache')) {

						$curl = curl_init();

						curl_setopt($curl, CURLOPT_URL, 'https://api-free.deepl.com/v2/translate');
						curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($curl, CURLOPT_FAILONERROR, false);
						curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
						curl_setopt($curl, CURLOPT_HEADER, false);
						curl_setopt($curl, CURLOPT_TIMEOUT, 15);
						curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
						curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($curl, CURLOPT_USERAGENT, 'Simple PHP Downloader');

						curl_setopt($curl, CURLOPT_POST, true);
						curl_setopt($curl, CURLOPT_POSTFIELDS, [
							'auth_key' => $apikey,
							'source_lang' => $from,
							'target_lang' => $to,
							'text' => $sentence->getOriginal(),
						]);

						if ($data = curl_exec($curl)) {
							$jsonResponse = json_decode($data);
							$text = $jsonResponse->translations[0]->text ?? null;
							if ($text) {
								$translation->set($text); // set new translation
							}

							// save only successful translations
							if ($text && $input->getOption('cache')) {
								$cache->save($translation);
							}
						}

						curl_close($curl);
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

		} catch (\Throwable $error) {
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