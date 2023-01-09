<?php

namespace potrans\translator;

use Gettext\Translation;

/**
 * There are two global variable $input and $output
 *
 * Usage:
 *
 * ./bin/potrans deepl ./tests/example-cs_CZ.po ~/Downloads --apikey=123456 --translator=src/translator/DeepLTranslatorEscaped.php --no-cache -vvv
 *
 * @var \Symfony\Component\Console\Input\InputInterface $input
 * @var \Symfony\Component\Console\Output\OutputInterface $output
 * @see \potrans\commands\DeepLTranslatorCommand for more information
 */
class DeepLTranslatorEscaped extends TranslatorAbstract {

	private \DeepL\Translator $translator;

	public function __construct(\DeepL\Translator $translator) {
		$this->translator = $translator;
	}

	/**
	 * @throws \DeepL\DeepLException
	 */
	public function getTranslation(Translation $sentence): string {
		$response = $this->translator->translateText(
			preg_replace('/\$([^$]+)\$/i', '<keep>$1</keep>', $sentence->getOriginal()),
			$this->from,
			$this->to,
			[
				'tag_handling' => 'xml',
				'ignore_tags' => 'keep',
			]
		);

		return preg_replace('/<\/?keep>/i', '$', $response->text);
	}
}

$apikey = (string) $input->getOption('apikey');

/**
 * Return custom translator instance
 */
return new DeepLTranslatorEscaped(
	new \DeepL\Translator($apikey),
);