<?php declare(strict_types=1);

use Gettext\Translation;
use potrans\translator\DeepLTranslator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


/**
 * Fake DeepL\Translator that captures input and returns a preset response
 */
class FakeDeepLTranslator extends \DeepL\Translator {
	public ?string $capturedText = null;
	public ?array $capturedOptions = null;
	private string $responseText;

	public function __construct(string $responseText) {
		// Skip parent constructor (needs API key)
		$this->responseText = $responseText;
	}

	public function translateText($texts, ?string $sourceLang, string $targetLang, array $options = []): \DeepL\TextResult {
		$this->capturedText = is_array($texts) ? $texts[0] : $texts;
		$this->capturedOptions = $options;

		return new \DeepL\TextResult($this->responseText, $targetLang, 0);
	}
}


test('getTranslation wraps ignored patterns in keep tags', function () {
	$fake = new FakeDeepLTranslator('přeloženo');
	$translator = new DeepLTranslator($fake, null, '\{[^}]+\}');
	$translator->from('en')->to('cs');

	$sentence = Translation::create(null, 'Hello {name}');
	$result = $translator->getTranslation($sentence);

	Assert::same('přeloženo', $result);
	Assert::contains('<keep>', $fake->capturedText);
	Assert::same('xml', $fake->capturedOptions['tag_handling']);
	Assert::same('keep', $fake->capturedOptions['ignore_tags']);
});


test('getTranslation strips keep tags from response', function () {
	$fake = new FakeDeepLTranslator('Ahoj <keep>{name}</keep>, vítejte');
	$translator = new DeepLTranslator($fake, null, '\{[^}]+\}');
	$translator->from('en')->to('cs');

	$sentence = Translation::create(null, 'Hello {name}, welcome');
	$result = $translator->getTranslation($sentence);

	Assert::same('Ahoj {name}, vítejte', $result);
});


test('getTranslation without regex sends text directly', function () {
	$fake = new FakeDeepLTranslator('Ahoj');
	$translator = new DeepLTranslator($fake);
	$translator->from('en')->to('cs');

	$sentence = Translation::create(null, 'Hello');
	$result = $translator->getTranslation($sentence);

	Assert::same('Ahoj', $result);
	Assert::notContains('<keep>', $fake->capturedText);
});


test('getTranslation decodes HTML entities in response', function () {
	$fake = new FakeDeepLTranslator('N&aacute;zev &amp; popis');
	$translator = new DeepLTranslator($fake);
	$translator->from('en')->to('cs');

	$sentence = Translation::create(null, 'Title & description');
	$result = $translator->getTranslation($sentence);

	Assert::same('Název & popis', $result);
});
