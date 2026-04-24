<?php declare(strict_types=1);

use Gettext\Translation;
use Gettext\Translations;
use potrans\PoTranslator;
use potrans\translator\Translator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


class NullTranslator implements Translator {
	public function from(string $from): Translator { return $this; }
	public function to(string $to): Translator { return $this; }
	public function getTranslation(Translation $sentence): string { return ''; }
}

class RecordingTranslator implements Translator {
	public array $called = [];
	public int $callCount = 0;
	public string $prefix = 'translated: ';

	public function from(string $from): Translator { return $this; }
	public function to(string $to): Translator { return $this; }
	public function getTranslation(Translation $sentence): string {
		$this->called[] = $sentence->getOriginal();
		$this->callCount++;
		return $this->prefix . $sentence->getOriginal();
	}
}


test('loadFile loads PO file correctly', function () {
	$potrans = new PoTranslator(new NullTranslator(), new ArrayAdapter());
	$translations = $potrans->loadFile(__DIR__ . '/example-cs_CZ.po');

	Assert::type(Translations::class, $translations);
	Assert::count(8, $translations);
});


test('savePoFile writes valid PO file', function () {
	$potrans = new PoTranslator(new NullTranslator(), new ArrayAdapter());
	$potrans->loadFile(__DIR__ . '/example-cs_CZ.po');

	$output = TEMP_DIR . '/output.po';
	$result = $potrans->savePoFile($output);

	Assert::true($result);
	Assert::true(file_exists($output));
	Assert::contains('msgid "Username"', file_get_contents($output));

	@unlink($output);
});


test('saveMoFile writes valid MO file', function () {
	$potrans = new PoTranslator(new NullTranslator(), new ArrayAdapter());
	$potrans->loadFile(__DIR__ . '/example-cs_CZ.po');

	$output = TEMP_DIR . '/output.mo';
	$result = $potrans->saveMoFile($output);

	Assert::true($result);
	Assert::true(file_exists($output));
	Assert::true(filesize($output) > 0);

	@unlink($output);
});


test('translate calls translator for untranslated sentences', function () {
	$recorder = new RecordingTranslator();

	$potrans = new PoTranslator($recorder, new ArrayAdapter());
	$potrans->loadFile(__DIR__ . '/example-cs_CZ.po');

	$results = [];
	foreach ($potrans->translate('en', 'cs') as $sentence) {
		$results[] = $sentence->getTranslation();
	}

	Assert::count(8, $results);
	Assert::count(8, $recorder->called);
	Assert::contains('translated: Username', $results);
	Assert::contains('translated: Password', $results);
});


test('translate skips already translated sentences when force is false', function () {
	$recorder = new RecordingTranslator();

	$translations = Translations::create('test');
	$t1 = Translation::create(null, 'Hello');
	$t2 = Translation::create(null, 'World');
	$t2->translate('Svět');
	$translations->add($t1);
	$translations->add($t2);

	$tmpFile = TEMP_DIR . '/partial.po';
	(new \Gettext\Generator\PoGenerator())->generateFile($translations, $tmpFile);

	$potrans = new PoTranslator($recorder, new ArrayAdapter());
	$potrans->loadFile($tmpFile);

	iterator_to_array($potrans->translate('en', 'cs', false));

	Assert::same(1, $recorder->callCount);

	@unlink($tmpFile);
});


test('translate re-translates everything when force is true', function () {
	$recorder = new RecordingTranslator();

	$translations = Translations::create('test');
	$t1 = Translation::create(null, 'Hello');
	$t2 = Translation::create(null, 'World');
	$t2->translate('Svět');
	$translations->add($t1);
	$translations->add($t2);

	$tmpFile = TEMP_DIR . '/force.po';
	(new \Gettext\Generator\PoGenerator())->generateFile($translations, $tmpFile);

	$potrans = new PoTranslator($recorder, new ArrayAdapter());
	$potrans->loadFile($tmpFile);

	iterator_to_array($potrans->translate('en', 'cs', true));

	Assert::same(2, $recorder->callCount);

	@unlink($tmpFile);
});


test('translate uses cache for repeated translations', function () {
	$recorder = new RecordingTranslator();
	$cache = new ArrayAdapter();

	$potrans = new PoTranslator($recorder, $cache);
	$potrans->loadFile(__DIR__ . '/example-cs_CZ.po');

	// First run - populates cache
	iterator_to_array($potrans->translate('en', 'cs'));
	$firstRunCount = $recorder->callCount;

	// Second run with same cache - should use cached values
	$potrans2 = new PoTranslator($recorder, $cache);
	$potrans2->loadFile(__DIR__ . '/example-cs_CZ.po');
	iterator_to_array($potrans2->translate('en', 'cs'));

	Assert::same($firstRunCount, $recorder->callCount);
});
