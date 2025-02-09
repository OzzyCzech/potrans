<?php

namespace potrans\translator;

use Gettext\Translation;

class DeepLTranslator extends TranslatorAbstract {

	private \DeepL\Translator $translator;
	private ?string $regex;

	public function __construct(\DeepL\Translator $translator, string $regex = null) {
		$this->translator = $translator;
		$this->regex = $regex;
	}

	/**
	 * @throws \DeepL\DeepLException
	 */
	public function getTranslation(Translation $sentence): string {
		$text = $sentence->getOriginal();
		$response = $this->translator->translateText(
			$this->regex ? preg_replace('/(' . $this->regex . ')/', '<keep>$1</keep>', $text) : $text,
			$this->from,
			$this->to,
			[
				'tag_handling' => 'xml',
				'ignore_tags' => 'keep',
			]
		);

		return preg_replace('/<\/?keep>/i', '', $response->text);
	}
}
