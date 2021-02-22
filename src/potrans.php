<?php

namespace potrans;

if (php_sapi_name() != 'cli') {
	die('Must run from command line');
}

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);
ini_set('log_errors', 0);
ini_set('html_errors', 0);

use cli\Arguments;
use cli\Colors;
use cli\progress\Bar;
use Sepia\PoParser\Catalog\Entry;
use Sepia\PoParser\Parser;
use Sepia\PoParser\PoCompiler;
use Sepia\PoParser\SourceHandler\FileSystem;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
	require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
	require_once __DIR__ . '/../../../autoload.php';
}

Colors::enable();

$strict = in_array('--strict', $_SERVER['argv']);
$arguments = new Arguments(compact('strict'));

// options
$arguments->addOption(['apikey', 'k'], ['description' => 'Google Translate API Key']);
$arguments->addOption(['input', 'i'], ['description' => 'Path to input PO file']);
$arguments->addOption(['output', 'o'], ['description' => 'Path to output PO file (default: ./tmp/*.po)']);
$arguments->addOption(['wait', 'w'], ['default' => 0, 'description' => 'Wait between requests in microsecond']);
$arguments->addOption(['from', 'f'], ['default' => 'en', 'description' => 'Source language (default: en)']);
$arguments->addOption(['to', 't'], ['default' => 'cs', 'description' => 'Target language (default: cs)']);

// flags
$arguments->addFlag(['verbose', 'v'], 'Turn on verbose output');
$arguments->addFlag(['help', 'h'], 'Show help');
$arguments->parse();

$apikey = $arguments['apikey'] ?? null;
$input = $arguments['input'] ?? null;
$output = $arguments['output'] ?? __DIR__ . '/../tmp/' . basename($input);
$from = $arguments['from'] ?? 'en';
$to = $arguments['to'] ?? 'cs';
$wait = $arguments['w'] ?? 0;
$verbose = $arguments['verbose'] ?? false;

if ($arguments['help'] || !$apikey || !$input) {
	echo str_repeat('-', 80) . PHP_EOL;
	echo 'PO translator parametters ' . PHP_EOL;
	echo str_repeat('-', 80) . PHP_EOL;
	# waiting for
	//echo $arguments->getHelpScreen();
	echo PHP_EOL . PHP_EOL;
	echo 'Example' . PHP_EOL;
	echo '  potrans --apikey 123456789 --input tests/example-cs_CZ.po --verbose';
	exit(PHP_EOL . PHP_EOL);
}

if (!file_exists($input)) {
	die(Colors::colorize(sprintf("%%rFile \"%s\" does not exists %%n%s", var_export($input, true), PHP_EOL)));
}

if (!is_dir(dirname($output))) {
	mkdir(dirname($output), 0755, true);
	// Failed to create dir
	if (!is_dir(dirname($output))) {
		die(Colors::colorize(sprintf("%%rDirectory \"%s\" does not exists %%n%s", dirname($output), PHP_EOL)));
	}
}

if (!is_dir($tmp = __DIR__ . '/../tmp')) mkdir($tmp, 0777);

try {
	// translator
	$translator = new Translator($apikey);
	$translator->setCacheDir($tmp);
	$translator->httpOptions[CURLOPT_FAILONERROR] = false;
	$translator->httpOptions[CURLOPT_HTTP200ALIASES] = [400];

	// input parsers
	$poInput = new Parser(new FileSystem($input));
	$inputCatalog = $poInput->parse();

	// output parsers
	$outputHandler = $output ? new FileSystem($output) : null;
	$poOutput = file_exists($output) ? new Parser($outputHandler) : false;
	$outputCatalog = $poOutput ? $poOutput->parse() : false;

	// headline
	printf("%s\n Translating %s entitites from %s to %s\n%s\n", str_repeat('-', 80), count($inputCatalog->getEntries()), $from, $to, str_repeat('-', 80));
	$progress = new Bar('Translate status ', count($inputCatalog->getEntries()));

	// translate Entries
	/** @var Entry $entry */
	foreach ($inputCatalog->getEntries() as $entry) {

		if ($outputCatalog && $translated = $outputCatalog->getEntry($entry->getMsgId())) {
			$entry->setMsgStr($translated->getMsgStr()); // already in ouput file
			if ($verbose) printf("> Skipped %s\n", $entry->getMsgId());
		} else {
			if ($wait) usleep($wait); // sleep between requests

			$translate = $translator->translate($entry->getMsgId(), $from, $to);
			$entry->setMsgStr($translate);
			if ($verbose) printf("> Translate: %s\n", $entry->getMsgId());
		}

		if (!$verbose) {
			$progress->tick();
		}
	}

	if (!$verbose) $progress->finish();

	// save output

	$compiler = new PoCompiler();
	if ($output) {
		printf("Save output to: %s\n", $output);
		$outputHandler->save($compiler->compile($inputCatalog));
	} else {
		echo $compiler->compile($inputCatalog);
	}

} catch (DownloadException $e) {
	$response = @json_decode($e->getResponse());
	if (isset($response->error->errors[0]->reason) && $response->error->errors[0]->reason === 'keyInvalid') {
		die(Colors::colorize('%rInvalid Google Translate API key%n' . PHP_EOL));
	} else {
		die(Colors::colorize('%rError "' . $e->getMessage() . '"%n' . PHP_EOL));
	}
} catch (\Exception $e) {
	die(Colors::colorize('%rInput parsing exception%n: ' . strval($e) . PHP_EOL));
}