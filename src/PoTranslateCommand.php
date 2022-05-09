<?php

namespace potrans;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations;
use potrans\translators\DeepLTranslator;
use potrans\translators\GoogleTranslator;
use potrans\translators\ITranslator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PoTranslateCommand extends Command {

	protected static $defaultName = 'po:translate';

	protected function configure(): void {

		$this->addArgument('input', InputArgument::REQUIRED, 'Input PO file path')
			->addArgument('output', InputArgument::OPTIONAL, 'Output PO, MO files directory', '~/Downloads')
			->addOption('translator', null, InputOption::VALUE_REQUIRED, 'Use service google or deepl', 'google')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source language (default: en)', 'en')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target language (default: cs)', 'cs')
			->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Wait between translations in milliseconds', false)
			->addOption('all', null, InputOption::VALUE_NONE, 'Re-translate including translated sentences');
	}

	public function loadPoFile(string $file): Translations {
		if (!file_exists($file)) {
			throw new RuntimeException(sprintf('Input file "%s" not found', $file));
		}

		$po = new PoLoader();
		return $po->loadFile($file);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// input PO file loading
		$inputFile = $input->getArgument('input');
		$wait = $input->getOption('wait');
		$translations = $this->loadPoFile($inputFile);

		// language pair
		$from = $input->getOption('from');
		$to = $input->getOption('to');

		// api key
		$credentials = $input->getOption('credentials');
		if (!$credentials) {
			throw new InvalidOptionException('Missing credentials or API key');
		}

		// output directory
		$outputDir = $input->getArgument('output');
		if (!$outputDir) {
			$outputDir = pathinfo($inputFile, PATHINFO_DIRNAME) . '/';
		}

		// load translator

		switch ($service = $input->getOption('translator')) {
			case 'deepl':
				$translator = new DeepLTranslator();
				$translator->setKey($input->getOption('apikey'));
				break;
			case 'google':
				$translator = new GoogleTranslator();
				$translator->setCredentials($input->getOption('credentials'));
				break;
			default:
				throw new InvalidOptionException('Unknown translator name ' . $service);
		}

		$translator = $this->loadService();
		$translator->setCredentials($credentials);

		$output->writeln(
			[
				'------------------------------',
				' PO trans translator',
				'------------------------------',
				'Input: ' . $inputFile,
				'Translator: ' . get_class($translator),
				'Translate: from ' . $from . ' to ' . $to,
				'Output dir: ' . $outputDir,
				'Translating:',
			]
		);

		$progress = new ProgressBar($output, count($translations));

		$translated = 0; // counter
		/** @var Translation $sentence */
		foreach ($translations as $sentence) {

			if (!$sentence->getTranslation() || $input->getOption('all')) {
				// translated counter
				$translated++;

				// translate with translator
				$sentence->translate(
					$translator->translate($sentence->getOriginal(), $from, $to)
				);

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
			if ($output->isVeryVerbose() === false) {
				$progress->advance();
			}

			if ($wait) usleep($wait);
		}

		if ($output->isVeryVerbose() === false) {
			$progress->finish();
			$output->writeln('');
		}

		$output->writeln('Translated sentences: ' . $translated);

		// MO file output
		if ($output->isVeryVerbose()) {
			$output->writeln(['...outputting MO File']);
		}
		$moGenerator = new MoGenerator();
		$moGenerator->generateFile($translations, $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.mo');

		// PO file output
		if ($output->isVeryVerbose()) {
			$output->writeln(['...outputting PO File']);
		}
		$poGenerator = new PoGenerator();
		$poGenerator->generateFile($translations, $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.po');

		// done!
		$output->writeln('DONE!');

		return Command::SUCCESS;
	}
}