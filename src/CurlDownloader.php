<?php

namespace potrans;

trait CurlDownloader {

	/** @var int */
	public int $cacheExpire = 86400; // 24h

	/** @var string */
	public string $cacheDir;

	/** @var string */
	public string $cachePrefix;

	public array $httpOptions = [
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
	 * @param int $cacheExpire
	 * @param string $prefix
	 * @return array|mixed|string
	 * @throws DownloadException
	 */
	public function cachedRequest(string $url, array $params = [], string $method = 'GET', int $cacheExpire = null, string $prefix = null) {
		if (!$this->cacheDir) {
			return $this->request($url, $params, $method);
		}

		if (is_null($prefix)) {
			$prefix = $this->cachePrefix ?? preg_replace('/.*([\w]+)$/iU', '$1_', get_called_class());
		}

		if (is_null($cacheExpire)) {
			$cacheExpire = $this->cacheExpire;
		}

		$cacheFile = $this->cacheDir . '/' . $prefix . md5($url . $method . json_encode($params));
		$response = @file_get_contents($cacheFile); // intentionally @
		if ($response && @filemtime($cacheFile) + $cacheExpire > time()) { // intentionally @
			return $response;
		}

		$response = $this->request($url, $params, $method);
		file_put_contents($cacheFile, $response);

		return $response;
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param string $method
	 * @return bool|string
	 * @throws DownloadException
	 */
	public function request(string $url, array $params = [], string $method = 'GET') {
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
			throw new DownloadException("Server Error: " . curl_error($curl), $code, null, $response);
		}

		if ($code >= 400) {
			throw new DownloadException("Server Error #$code: " . curl_error($curl), $code, null, $response);
		}

		curl_close($curl);

		return $response;
	}

	/**
	 * @return int
	 */
	public function getCacheExpire(): int {
		return $this->cacheExpire;
	}

	/**
	 * @param int $cacheExpire
	 */
	public function setCacheExpire(int $cacheExpire): void {
		$this->cacheExpire = $cacheExpire;
	}

	/**
	 * @return string
	 */
	public function getCacheDir(): string {
		return $this->cacheDir;
	}

	/**
	 * @param string $cacheDir
	 */
	public function setCacheDir(string $cacheDir): void {
		$this->cacheDir = $cacheDir;
	}

	/**
	 * @return string
	 */
	public function getCachePrefix(): string {
		return $this->cachePrefix;
	}

	/**
	 * @param string $cachePrefix
	 */
	public function setCachePrefix(string $cachePrefix): void {
		$this->cachePrefix = $cachePrefix;
	}

}