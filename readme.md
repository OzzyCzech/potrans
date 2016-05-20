# Potrans

Potrans it's PHP command line tool for automatic translation of Gettext PO file with Google Translator API.

## Google Translator API

Google Translate API pricing is based on usage. Translation usage is calculated in millions of
characters (M), where 1 M = 10^6 characters. For more information, see the [Pricing FAQ](https://developers.google.com/translate/v2/faq#pricing).

For more information about Google Translate API visit https://developers.google.com/translate/

See supported languages: https://developers.google.com/translate/v2/using_rest#language-params

### Getting API Key

1. Go to the [Google Cloud Console](https://console.developers.google.com/).
2. Select a project.
3. In the sidebar on the left, select APIs & auth. In the displayed list of APIs, make sure the Google Translate API status is set to ON.
4. In the sidebar on the left, select Registered apps.
5. Select an application.

See full Getting Started guide: https://developers.google.com/translate/v2/getting_started

## Installation

* Install composer `curl -s http://getcomposer.org/installer | php` then run `composer install` for install all dependencies. For more information about Composer visit: https://getcomposer.org
* Install PHP Curl extension (php5-curl)

## Example

Follow example will translate whole content of `members-cs_CZ.po` from English (default) to Czech language (default)

    potrans -k 123456789 -i members-cs_CZ.po -v

Another example it's about output.

    potrans -k 123456789 -i example_RU.po -o path/to/output_EN.po -f ru -t en


## Help

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
      --from, -f    Source language (default: en) [default: en]
      --to, -t      Target language (default: cs) [default: cs]

    Example
      potrans -k 123456789 -i members-cs_CZ.po -v
