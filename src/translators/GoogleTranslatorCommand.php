<?php

namespace potrans\translators;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations;
use Google\Cloud\Translate\V3\TranslationServiceClient;
use potrans\translators\DeepLTranslator;
use potrans\translators\GoogleTranslator;
use potrans\translators\ITranslator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GoogleTranslatorCommand extends Command {

	protected function configure(): void {
		$this->addArgument('input', InputArgument::REQUIRED, 'Input PO file path')
			->addArgument('output', InputArgument::OPTIONAL, 'Output PO, MO files directory', '~/Downloads')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source language (default: en)', 'en')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target language (default: cs)', 'cs')
			->addOption('all', null, InputOption::VALUE_NONE, 'Re-translate including translated sentences')
			->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Wait between translations in milliseconds', false)
			->addOption('credentials', null, InputOption::VALUE_REQUIRED, 'Path to Google Credentials file', './credentials.json')
			->addOption('project', null, InputOption::VALUE_REQUIRED, 'Google Cloud Project ID')
			->addOption('location', null, InputOption::VALUE_REQUIRED, 'Google Cloud Location', 'global');
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
	protected function execute(InputInterface $input, OutputInterface $output) {
		$from = $input->getOption('from');
		$to = $input->getOption('to');
		$inputFile = $input->getArgument('input');
		$location = $input->getOption('location');
		$wait = $input->getOption('wait');

		// load translations
		$translations = $this->loadPoFile($inputFile);

		// credentials
		$credentials = $input->getOption('credentials');
		if (!is_file($credentials)) {
			throw new InvalidOptionException('Missing credentials or API key');
		}

		// project
		$project = $input->getOption('project');
		if (!$project) {
			throw new InvalidOptionException('Project ID is required');
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
				'Input: ' . $inputFile,
				'Translate: from ' . $from . ' to ' . $to,
				'Output dir: ' . $outputDir,
				'Translating:',
			]
		);

		$progress = new ProgressBar($output, count($translations));
		$translator = new TranslationServiceClient(['credentials' => $credentials]);

		$translated = 0; // counter
		/** @var Translation $sentence */
		foreach ($translations as $sentence) {

			if (!$sentence->getTranslation() || $input->getOption('all')) {
				// translated counter
				$translated++;

				$response = $translator->translateText(
					[$sentence->getOriginal()],
					$to,
					TranslationServiceClient::locationName($project, $location)
				);

				$sentence->translate($response->getTranslations()[0]->getTranslatedText());

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

		$output->writeln('Translated sentences count: ' . $translated);

		// MO file output
		$moGenerator = new MoGenerator();
		$moOutputFile = $outputDir . DIRECTORY_SEPARATOR . pathinfo($inputFile, PATHINFO_FILENAME) . '.mo';
		if ($output->isVeryVerbose()) {
			$output->writeln('Writing new MO File: ' . $moOutputFile);
		}
		$moGenerator->generateFile($translations, $moOutputFile);

		// PO file output
		$poGenerator = new PoGenerator();
		$poOutputFile = $outputDir . DIRECTORY_SEPARATOR . pathinfo($inputFile, PATHINFO_FILENAME) . '.po';
		if ($output->isVeryVerbose()) {
			$output->writeln('Writing new PO File: ' . $poOutputFile);
		}
		$poGenerator->generateFile($translations, $poOutputFile);

		// done!
		$output->writeln('DONE!');

		return Command::SUCCESS;
	}

	public function getDescription(): string {
		return 'Translate PO file with Google Translator';
	}

}