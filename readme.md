<div align="center">

![Packagist Version](https://img.shields.io/packagist/v/om/potrans?style=for-the-badge)
![Packagist License](https://img.shields.io/packagist/l/om/potrans?style=for-the-badge)
![Packagist Downloads](https://img.shields.io/packagist/dm/om/potrans?style=for-the-badge)

</div>

# PO file translator

Potrans it's PHP command line tool for automatic translation of [Gettext](https://www.gnu.org/software/gettext/) PO file with
[Google Translator](https://cloud.google.com/translate) or [DeepL Translator](https://www.deepl.com/).

## Google Translator

```shell
bin/potrans google --help
```

```text
Description:
  Translate PO file with Google Translator API

Usage:
  google [options] [--] <input> [<output>]

Arguments:
  input                          Input PO file path
  output                         Output PO, MO files directory [default: "~/Downloads"]

Options:
      --from=FROM                Source language (default: en) [default: "en"]
      --to=TO                    Target language (default: cs) [default: "cs"]
      --force                    Force re-translate including translated sentences
      --wait=WAIT                Wait between translations in milliseconds [default: false]
      --credentials=CREDENTIALS  Path to Google Credentials file [default: "./credentials.json"]
      --project=PROJECT          Google Cloud Project ID [default: project_id from credentials.json]
      --location=LOCATION        Google Cloud Location [default: "global"]
      --translator[=TRANSLATOR]  Path to custom translator instance
      --cache|--no-cache         Load from cache or not
  -h, --help                     Display help for the given command. When no command is given display help for the list command
  -q, --quiet                    Do not output any message
  -V, --version                  Display this application version
      --ansi|--no-ansi           Force (or disable --no-ansi) ANSI output
  -n, --no-interaction           Do not ask any interactive question
  -v|vv|vvv, --verbose           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

### Example commands

Follow command will translate whole content of `tests/example-cs_CZ.po` from English (default) to Czech language (default):

```bash
bin/potrans google tests/example-cs_CZ.po ~/Downloads \
  --credentials=your-credentials-file.json
```

You can also change source and target language with `--form` and `--to` parametters:

```bash
bin/potrans google tests/example-cs_CZ.po ~/Downloads \
  --credentials=your-credentials-file.json \
  --from=en \
  --to=de
```

### Google Translate API Pricing

Google Translate API pricing is based on usage. Translation usage is calculated in millions
of characters (M), where 1 M = 10^6 characters. For more information, see the
[Pricing FAQ](https://cloud.google.com/translate/pricing).

* [Translaton API](https://cloud.google.com/translate)
* [Quick Starts](https://cloud.google.com/translate/docs/quickstarts)
* [Supported languages](https://developers.google.com/translate/v2/using_rest#language-params)

### Getting Google Translation Credentials

1. Open [Google Cloud Console](https://console.cloud.google.com/) website
2. Create a new **Project** (or select existing one)
3. Search for [translate API](https://cloud.google.com/translate/docs/apis) and enable it then
4. Go to [IAM & Admin](https://console.cloud.google.com/iam-admin/iam) > *Service Accounts* and click to **+ Create service account**
5. Chose *Service account name* and *Service account ID* and click to **Create and continue**
6. Grant this service account access to project and add follow roles **Cloud Translation API Editor**, **AutoML Editor**
7. Create new Keys and **download credentials JSON file**

You can watch it here:

[<img src="https://img.youtube.com/vi/SCyP1AN2-EE/maxresdefault.jpg" width="50%" style="margin:auto">](https://www.youtube.com/watch?v=SCyP1AN2-EE)

## DeepL Translator

```shell
bin/potrans deepl --help
```

```text
Description:
  Translate PO file with DeepL Translator API

Usage:
  deepl [options] [--] <input> [<output>]

Arguments:
  input                          Input PO file path
  output                         Output PO, MO files directory [default: "~/Downloads"]

Options:
      --from=FROM                Source language (default: en) [default: "en"]
      --to=TO                    Target language (default: cs) [default: "cs"]
      --force                    Force re-translate including translated sentences
      --wait=WAIT                Wait between translations in milliseconds [default: false]
      --apikey=APIKEY            Deepl API Key
      --translator[=TRANSLATOR]  Path to custom translator instance
      --cache|--no-cache         Load from cache or not
  -h, --help                     Display help for the given command. When no command is given display help for the list command
  -q, --quiet                    Do not output any message
  -V, --version                  Display this application version
      --ansi|--no-ansi           Force (or disable --no-ansi) ANSI output
  -n, --no-interaction           Do not ask any interactive question
  -v|vv|vvv, --verbose           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

```

### Example commands

```shell
bin/potrans deepl tests/example-cs_CZ.po ~/Downloads --apikey=123456
```

### DeepL Translator API pricing

DeepL translator [API pricing](https://www.deepl.com/pro-api) is based on monthly subscription.
There is max. 500,000 characters/month for free.

For more information visit https://www.deepl.com/pro-api

### Getting Api Key

1. Register [free Pro Account](https://www.deepl.com/pro)
2. Visit [Your DeepL account](https://www.deepl.com/cs/your-account/keys)
3. Search for Authentication Key for DeepL API or create new one

## Install

```shell
composer require --dev om/potrans
```

## Custom translator

If you need to use a custom translator that behaves differently than the original translator.
You have the option to use the `--translator` parameter like follow:

```shell
./bin/potrans deepl ./tests/example-cs_CZ.po ~/Downloads \
    --translator=path/to/my/CustomTranslator.php \
    --apikey=123456
```

PHP file should contain implementation of `Translator` interface and should return new instance:

```php
<?php
class CustomTranslator implements \potrans\translator\Translator {
  // TODO add your code
}

return new CustomTranslator(); 
```

You can find an example custom translator in the file [DeepLTranslatorEscaped.php](https://github.com/OzzyCzech/potrans/blob/master/src/translator/DeepLTranslatorEscaped.php)

## Potrans development

1. Install composer `curl -s http://getcomposer.org/installer | php`
2. Run `composer install` for install all dependencies
3. Install PHP Curl extension (curl and json PHP extensions)

For more information about Composer visit: [https://getcomposer.org](https://getcomposer.org)

If you had `"command not found: potrans"` return, just run the command like this: `php bin/potrans` and will run without problems.

## Troubleshooting

#### cURL error: SSL certificate issue (Google Translate only)

You may encounter a problem caused by cURL like follow:

```text
cURL error 60: SSL certificate problem: unable to get local issuer certificate (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
```

There is missing issuer certificate `cacert.pem` file and curl won't verify SSL requests:

1. Download [http://curl.haxx.se/ca/cacert.pem](http://curl.haxx.se/ca/cacert.pem)
2. Save is somewhere e.g. `/usr/local/etc/cacert.pem`
3. Update your `php.ini` with following:

```ini
curl.cainfo = "/usr/local/etc/cacert.pem"
openssl.cafile = "/usr/local/etc/cacert.pem"
```

You can verify it with `phpinfo()` or `php --info`. Read more detailed [instruction here](https://stackoverflow.com/a/32095378/355316).

## Links

* [GNU gettext utilities](https://www.gnu.org/software/gettext/manual/html_node/)
* [PHP Gettext](https://github.com/php-gettext/Gettext)
