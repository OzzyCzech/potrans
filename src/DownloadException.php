<?php

namespace potrans;

class DownloadException extends \Exception {

	private $response;

	public function __construct(string $message = "", int $code = 0, \Exception $previous = null, $response = null) {
		parent::__construct($message, $code, $previous);
		$this->response = $response;
	}

	public function getResponse() {
		return $this->response;
	}

}