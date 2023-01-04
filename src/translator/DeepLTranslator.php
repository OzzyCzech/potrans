<?php

namespace potrans\translator;

use Gettext\Translation;

class DeepLTranslator extends TranslatorAbstract {

	private \DeepL\Translator $translator;

	public function __construct(\DeepL\Translator $translator) {
		$this->translator = $translator;
	}

	/**
	 * @throws \DeepL\DeepLException
	 */
	public function getTranslation(Translation $sentence): string {
		$response = $this->translator->translateText(
			$sentence->getOriginal(),
			$this->from,
			$this->to,
		);

		return $response->text;
	}
}