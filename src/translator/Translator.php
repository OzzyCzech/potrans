<?php

namespace potrans\translator;

use Gettext\Translation;

interface Translator {

	/**
	 * @param string $from
	 * @return \potrans\translator\Translator
	 */
	public function from(string $from): Translator;

	/**
	 * @param string $to
	 * @return \potrans\translator\Translator
	 */
	public function to(string $to): Translator;

	/**
	 * @param \Gettext\Translation $sentence
	 * @return string
	 */
	public function getTranslation(Translation $sentence): string;
}