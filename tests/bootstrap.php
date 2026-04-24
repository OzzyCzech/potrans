<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();
Tester\Environment::setupFunctions();

define('TEMP_DIR', __DIR__ . '/temp');
@mkdir(TEMP_DIR);
