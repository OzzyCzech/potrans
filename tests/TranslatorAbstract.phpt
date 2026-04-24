<?php declare(strict_types=1);

use Gettext\Translation;
use potrans\translator\Translator;
use potrans\translator\TranslatorAbstract;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('from() and to() return Translator instance for chaining', function () {
	$translator = new class extends TranslatorAbstract {
		public function getTranslation(Translation $sentence): string { return ''; }
	};

	$result = $translator->from('en');
	Assert::type(Translator::class, $result);

	$result = $translator->to('cs');
	Assert::type(Translator::class, $result);
});


test('from() and to() are chainable', function () {
	$translator = new class extends TranslatorAbstract {
		public function getTranslation(Translation $sentence): string { return ''; }
		public function getFrom(): string { return $this->from; }
		public function getTo(): string { return $this->to; }
	};

	$translator->from('en')->to('cs');

	Assert::same('en', $translator->getFrom());
	Assert::same('cs', $translator->getTo());
});
