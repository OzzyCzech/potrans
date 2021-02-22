<?php

namespace potrans;

class Translator {

	use CurlDownloader;

	const API_URL = 'https://www.googleapis.com/language/translate/v2';

	protected string $apiKey;

	public function __construct(string $apiKey) {
		$this->apiKey = $apiKey;
	}

	/**
	 * Translate string with Google API
	 * @param string $text
	 * @param string $source
	 * @param string $target
	 * @return mixed
	 * @throws DownloadException
	 */
	public function translate(string $text, string $source = 'en', string $target = 'cs') {
		$result = $this->cachedRequest(
			self::API_URL,
			[
				'key' => $this->apiKey,
				'q' => $text,
				'source' => $source,
				'target' => $target,
				'format' => 'text',
			]
		);

		if ($result && $result = json_decode($result)) {
			if (isset($result->data->translations[0])) {
				return $result->data->translations[0]->translatedText;
			}
		}
	}
}