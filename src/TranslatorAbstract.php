<?php

namespace potrans;

use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations;
use potrans\translator\Translator;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Console\Exception\RuntimeException;

abstract class TranslatorAbstract implements Translator {

	/** @var bool $translateAll re-translate everything again */
	protected bool $translateAll = false;
	/** @var \Gettext\Translations */
	private Translations $sentences;
	private string $from;
	private string $to;



	/**
	 * @param string $filename
	 * @return Translations
	 */
	public function loadFile(string $filename): Translations {
		return $this->sentences = (new PoLoader())->loadFile($filename);
	}

	public function getTranslations(string $from, string $to, ?callable $callback = null): Translations {
		/** @var \Gettext\Translation $sentence */
		foreach ($this->sentences as $sentence) {

			// sentence translation is missing or we re-translate everything
			if (!$sentence->isTranslated() || $this->translateAll) {
				$key = md5($sentence->getOriginal() . $from . $to);
				/** @var \Symfony\Component\Cache\CacheItem $translation */

				$translation = $this->getCache()->get($key);

				if (!$translation->isHit()) {
					// translate sentence
					$translation->set($this->getTranslation($from, $to, $sentence->getOriginal()));

					// save cache
					$this->getCache()->save($translation);
				}

				$sentence->translate($translation->get());

				if ($callback) $callback($sentence);
			}

		}

		return $this->sentences;
	}

	abstract protected function getCache(): AdapterInterface;



}