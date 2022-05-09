<?php

namespace potrans;

use potrans\translators\DeepLTranslatorCommand;
use potrans\translators\GoogleTranslatorCommand;
use potrans\translators\BaseInputDefinition;
use Symfony\Component\Console\Application;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
	require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
	require_once __DIR__ . '/../../../autoload.php';
}

$application = new Application();
$application->addCommands(
	[
		new GoogleTranslatorCommand('google'),
		//new DeepLTranslatorCommand(new BaseInputDefinition()),
	]
);
$application->run();