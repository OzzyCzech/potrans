<?php
namespace potrans;

use om\CurlDownloader;

class Translator {
	use CurlDownloader;

	const API_URL = 'https://www.googleapis.com/language/translate/v2';

	/** @var null */
	protected $apiKey;


	/**
	 * @param null $apiKey
	 */
	public function __construct($apiKey = null) {
		$this->apiKey = $apiKey;
	}

	/**
	 * Translate string using Google translator API
	 *
	 * @param $text
	 * @param string $source
	 * @param string $target
	 * @return mixed|null
	 */
	public function translate($text, $source = 'en', $target = 'cs') {
            $result = $this->cachedRequest(
                self::API_URL,
                [
                    'key' => $this->apiKey,
                    'q' => $text,
                    'source' => $source,
                    'target' => $target,
                    'format' => 'text'
                ]
            );

            if ($result && $result = json_decode($result)) {
                if (isset($result->data->translations[0])) {
                        return $result->data->translations[0]->translatedText;
                }
            }
	}
}