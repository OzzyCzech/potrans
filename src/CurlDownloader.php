<?php
/**
 * @author Roman Ožana <ozana@omdesign.cz>
 */

namespace om {

	trait CurlDownloader {

		/** @var int */
		public static $cacheExpire = 86400; // 24h

		/** @var string */
		public static $cacheDir;

		/** @var string */
		public static $cachePrefix;

		public $httpOptions = [
			CURLOPT_FAILONERROR => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => 'Simple PHP Downloader',
		];

		/**
		 * @param string $url
		 * @param array $params
		 * @param string $method
		 * @param null $cacheExpire
		 * @param string $prefix
		 * @return array|mixed|string
		 */
		public function cachedRequest($url, array $params = [], $method = 'GET', $cacheExpire = null, $prefix = null) {
			if (!static::$cacheDir) {
				return $this->request($url, $params, $method);
			}

			if ($prefix === null) {
				$prefix = static::$cachePrefix ? static::$cachePrefix : preg_replace(
					'/.*([\w]+)$/iU', '$1_', get_called_class()
				);
			}

			if ($cacheExpire === null) {
				$cacheExpire = static::$cacheExpire;
			}

			$cacheFile = static::$cacheDir . '/' . $prefix . md5($url . $method . json_encode($params));
			$response = @file_get_contents($cacheFile); // intentionally @
			if ($response && @filemtime($cacheFile) + $cacheExpire > time()) { // intentionally @
				return $response;
			}

			$response = $this->request($url, $params, $method);
			file_put_contents($cacheFile, $response);

			return $response;
		}

		/**
		 * @param $url
		 * @param string $method
		 * @param array $params
		 * @return mixed
		 * @throws \DownloadException
		 * @throws \Exception
		 */
		public function request($url, $params = [], $method = 'GET') {
			$options = [
					CURLOPT_HEADER => false,
					CURLOPT_RETURNTRANSFER => true,
				] + ($method === 'POST' ? [
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => http_build_query($params),
					CURLOPT_URL => $url,
				] : [
					CURLOPT_URL => $url . ($params ? '?' . http_build_query($params) : null),
				]) + $this->httpOptions;

			$curl = curl_init($url);
			curl_setopt_array($curl, $options);

			$response = curl_exec($curl);
			$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			if (curl_errno($curl)) {
				throw new \DownloadException("Server Error: " . curl_error($curl), $code, null, $response);
			}

			if ($code >= 400) {
				throw new \DownloadException("Server Error #$code: " . curl_error($curl), $code, null, $response);
			}

			curl_close($curl);

			return $response;
		}

		private function error() {

		}

	}
}

namespace {

	class DownloadException extends \Exception {

		/** @var string */
		private $response;

		public function __construct($message = "", $code = 0, Exception $previous = null, $response = null) {
			parent::__construct($message, $code, $previous);
			$this->response = $response;
		}

		public function getResponse() {
			return $this->response;
		}

	}
}
