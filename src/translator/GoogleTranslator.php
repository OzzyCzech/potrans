<?php

namespace potrans\translator;

use Gettext\Translation;
use Google\Cloud\Translate\V3\TranslationServiceClient;

class GoogleTranslator extends TranslatorAbstract {

	/** @var \Google\Cloud\Translate\V3\TranslationServiceClient */
	private TranslationServiceClient $translationServiceClient;
	private string $location;
	private string $project;

	/**
	 * @param \Google\Cloud\Translate\V3\TranslationServiceClient $translationServiceClient
	 * @param string $project
	 * @param string $location
	 */
	public function __construct(
		TranslationServiceClient $translationServiceClient,
		string $project,
		string $location
	) {
		$this->translationServiceClient = $translationServiceClient;
		$this->project = $project;
		$this->location = $location;
	}

	/**
	 * @param \Gettext\Translation $sentence
	 * @return string
	 * @throws \Google\ApiCore\ApiException
	 */
	public function getTranslation(Translation $sentence): string {
		$response = $this->translationServiceClient->translateText(
			[$sentence->getOriginal()],
			$this->to,
			TranslationServiceClient::locationName($this->project, $this->location),
			['sourceLanguageCode' => $this->from]
		);

		return $response->getTranslations()[0]->getTranslatedText();
	}
}