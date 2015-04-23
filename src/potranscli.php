<?php
namespace potrans;

/**
 * @author Roman Ozana <ozana@omdesign.cz>
 */
use cli\Arguments;
use cli\Colors;
use cli\progress\Bar;
use Sepia\PoParser;

require_once __DIR__ . '/../vendor/autoload.php';

Colors::enable();

$arguments = new Arguments(compact('strict'));

$arguments->addOption(['apikey', 'k'], ['description' => 'Google Translate API Key']);
$arguments->addOption(['input', 'i'], ['description' => 'Path to input PO file']);
$arguments->addOption(['output', 'o'], ['description' => 'Path to output PO file (default: ./tmp/*.po)']);
$arguments->addOption(['from', 'f'], ['default' => 'en', 'description' => 'Source language (default: en)']);
$arguments->addOption(['to', 't'], ['default' => 'cs', 'description' => 'Target language (default: cs)']);

$arguments->addFlag(['verbose', 'v'], 'Turn on verbose output');
$arguments->addFlag(['help', 'h'], 'Show help');
$arguments->parse();


$apikey = $arguments['apikey'];
$input = $arguments['input'];
$output = $arguments['output'] ? $arguments['output'] : __DIR__ . '/../tmp/' . basename($input);
$from = $arguments['from'] ? $arguments['from'] : 'en';
$to = $arguments['to'] ? $arguments['to'] : 'cs';
$verbose = (bool)$arguments['verbose'];


if ($arguments['help'] || !$apikey || !$input) {
	echo str_repeat('-', 80) . PHP_EOL;
	echo 'PO translator parametters ' . PHP_EOL;
	echo str_repeat('-', 80) . PHP_EOL;
	echo $arguments->getHelpScreen();
	echo PHP_EOL . PHP_EOL;
	echo 'Example' . PHP_EOL;
	echo '  potrans -k 123456789 -i members-cs_CZ.po -v';
	exit(PHP_EOL . PHP_EOL);
}


if (!file_exists($input)) {
	die(Colors::colorize('%rFile "' . print_r($input) . '" not exists %n' . PHP_EOL));
}

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0755, true);
    // Failed to create dir
    if (!is_dir(dirname($output))) {
	die(Colors::colorize('%rDirectory "' . dirname($output) . '" not exists %n' . PHP_EOL));
    }
}

if (!is_dir($tmp = __DIR__ . '/../tmp')) mkdir($tmp, 0777);
Translator::$cacheDir = $tmp;

try {
	// translator
	$translator = new Translator($arguments['apikey']);
	$translator->httpOptions[CURLOPT_FAILONERROR] = false;
	$translator->httpOptions[CURLOPT_HTTP200ALIASES] = [400];

	// parser
	$po = new PoParser();
	$entries = $po->read($input);
        
        $previousEntries = null;
        if (file_exists($output)) {
            $previousEntries = $po->read($output);
        }

	echo Colors::colorize('Translating : %b' . count($entries) . '%n entries from ' . $from . ' to ' . $to . PHP_EOL);
	$progress = new Bar('Translate status ', count($entries));

	foreach ($entries as $entry => $data) {
            $translate = "";
            $skipped = "";
            if (isset($previousEntries) && !empty($previousEntries[$entry]['msgstr'][0])) {
                $skipped = "(skipped)";
                $translate = $previousEntries[$entry]['msgstr'][0];
            } else {
                $translate = $translator->translate($entry, $from, $to);
            }
            
            $po->update_entry($entry, $translate);

            if ($verbose) {
                echo $verbose ? " $entry => $translate $skipped" . PHP_EOL : null;
            } else {
                $progress->tick();
            }
	}

	if (!$verbose) $progress->finish();

	echo 'Save output to: ' . $output . PHP_EOL;
	$po->write($output);

} catch (\DownloadException $e) {
	$response = @json_decode($e->getResponse());
	if (isset($response->error->errors[0]->reason) && $response->error->errors[0]->reason === 'keyInvalid') {
		die(Colors::colorize('%rInvalid Google Translate API key%n' . PHP_EOL));
	} else {
		die(Colors::colorize('%rError "' . $e->getMessage() . '"%n' . PHP_EOL));
	}
}