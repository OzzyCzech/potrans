<?php

namespace potrans\commands;

use DeepL\Translator;
use potrans\PoTranslator;
use potrans\translator\DeepLTranslator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Dotenv\Dotenv;

class DeepLTranslatorCommand extends Command {

	protected function configure(): void {
		$this->addArgument('input', InputArgument::REQUIRED, 'Input PO file path')
			->addArgument('output', InputArgument::OPTIONAL, 'Output PO, MO files directory')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source language (default: en)', 'en')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target language (default: derived from input file name)')
			->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Root directory (default: current working directory)')
			->addOption('force', null, InputOption::VALUE_NONE, 'Force re-translate including translated sentences')
			->addOption('only', null, InputOption::VALUE_NONE, 'Create only PO file, no MO file')
			->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Wait between translations in milliseconds', false)
			->addOption('apikey', null, InputOption::VALUE_REQUIRED, 'Deepl API Key')
			->addOption('translator', null, InputOption::VALUE_OPTIONAL, 'Path to custom translator instance', null)
			->addOption('cache', null, InputOption::VALUE_NEGATABLE, 'Load from cache or not', true);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {

			$dir = $input->getOption('dir') ?? getcwd();

			// Load .env file if it exists
			if (file_exists($dir . '/.env')) {
				Dotenv::createImmutable($dir)->load();
			}

			// Input PO file
			$inputFile = $input->getArgument('input');
			if ($inputFile[0] !== '/') {
				$inputFile = $dir . '/' . $inputFile;
			}

			if (!file_exists($inputFile)) {
				throw new RuntimeException(sprintf('Input file "%s" not found', $inputFile));
			}

			// Output directory
			$outputDir = $input->getArgument('output') ?? dirname($inputFile);
			if ($outputDir[0] !== '/') {
				$outputDir = $dir . '/' . $outputDir;
			}
			$outputDir = rtrim(realpath($outputDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			if (!is_dir($outputDir)) {
				throw new InvalidOptionException('Invalid directory path: ' . $outputDir);
			}

			// Get API key from .env or command line
			$apikey = $_ENV['DEEPL_API_KEY'] ?? $input->getOption('apikey');

			if (!$apikey) {
				throw new InvalidOptionException('DeepL API Key is required. Set it in .env file or use --apikey option.');
			}

			// Crete new DeepL translator
			$customTranslatorPath = $input->getOption('translator');
			if ($customTranslatorPath && file_exists($customTranslatorPath)) {
				$translator = require_once $customTranslatorPath;

				if (!$translator instanceof \potrans\translator\Translator) {
					throw new InvalidOptionException('Invalid translator instance: ' . $customTranslatorPath);
				}
			} else {
				$translator = new DeepLTranslator(
					new Translator($apikey),
				);
			}

			// Setup caching
			$cache = $input->getOption('cache') ?
				new FilesystemAdapter('deepl', 3600, 'cache') :
				new NullAdapter();

			// Read params
			$force = (bool) $input->getOption('force');
			$to = (string) ($input->getOption('to') ?? basename($inputFile, '.po'));
			$from = (string) $input->getOption('from');
			$wait = (int) $input->getOption('wait');

			$potrans = new PoTranslator($translator, $cache);
			$translations = $potrans->loadFile($inputFile);

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
			foreach ($potrans->translate($from, $to, $force) as $sentence) {
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

				// progress
				if (!$output->isVeryVerbose()) {
					$progress->advance();
				}

				$translated++;

				if ($wait) usleep($wait);
			}

			if (!$output->isVeryVerbose()) {
				$progress->finish();
				$output->writeln('');
			}

			$output->writeln('<comment>Translated :</comment> ' . $translated . ' sentences');

			// MO file output
			if(!$input->getOption('only')) {
				$moOutputFile = $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.mo';
				if ($output->isVeryVerbose()) {
					$output->writeln('<comment>Writing new MO File</comment>: ' . $moOutputFile);
				}
				$potrans->saveMoFile($moOutputFile);
			}

			// PO file output
			$poOutputFile = $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.po';
			if ($output->isVeryVerbose()) {
				$output->writeln('<comment>Writing new PO File</comment>: ' . $poOutputFile);
			}
			$potrans->savePoFile($poOutputFile);

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
