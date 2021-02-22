# Potrans

Potrans it's PHP command line tool for automatic translation of [Gettext](https://www.gnu.org/software/gettext/) PO file with [Google Translator API](https://cloud.google.com/translate).

## Google Translator API

Google Translate API pricing is based on usage. Translation usage is calculated in millions of
characters (M), where 1 M = 10^6 characters. For more information, see the [Pricing FAQ](https://cloud.google.com/translate/pricing).

For more information about Google Translate API visit https://developers.google.com/translate/

See supported languages: https://developers.google.com/translate/v2/using_rest#language-params

### Getting API Key

1. Go to the [Google Cloud Console](https://console.developers.google.com/).
2. Select a project.
3. Search for translate API and enable it
4. From the credentials interface create a new API Key.


3. In the sidebar on the left, select **APIs & auth**. In the displayed list of APIs, make sure the Google Translate API status is set to ON.
4. In the sidebar on the left, select Registered apps.
5. Select an application.

See full Getting Started guide: https://developers.google.com/translate/v2/getting_started

## Install

```
composer require --dev om/potrans
```

## Development

1. Install composer `curl -s http://getcomposer.org/installer | php` 
2. Run `composer install` for install all dependencies
3. Install PHP Curl extension (curl and json PHP extensions) 

For more information about Composer visit: [https://getcomposer.org](https://getcomposer.org)

If you had `"command not found: potrans"` return, just run the command like this: `php bin/potrans` and will run without problems.

## Example

Follow example will translate whole content of `tests/example-cs_CZ.po` from English (default) to Czech language (default)

```bash
./potrans --apikey 123456789 --input tests/example-cs_CZ.po --output tests/translated.po 
```

Another example it's about output.

```bash
./potrans --apikey 123456789 --input example_RU.po --output path/to/output_EN.po --from ru --to en
```

## Help

```text
--------------------------------------------------------------------------------
PO translator parametters
--------------------------------------------------------------------------------
Flags
  --verbose, -v  Turn on verbose output
  --help, -h     Show help

Options
  --apikey, -k  Google Translate API Key
  --input, -i   Path to input PO file
  --output, -o  Path to output PO file (default: ./tmp/*.po)
  --wait, -w    Wait between requests in microsecond
  --from, -f    Source language (default: en) [default: en]
  --to, -t      Target language (default: cs) [default: cs]

Example
  potrans -k 123456789 -i members-cs_CZ.po -v
```

## Links

* [GNU gettext utilities](https://www.gnu.org/software/gettext/manual/html_node/)
* [The Format of PO Files](https://www.gnu.org/software/gettext/manual/html_node/PO-Files.html)


