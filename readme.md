# PO trans

Potrans it's PHP command line tool for automatic translation of Gettext PO file with Google Translator API.

## Google Translator API

Google Translate API pricing is based on usage. Translation usage is calculated in millions of
characters (M), where 1 M = 106 characters. For more information, see the (Pricing FAQ)[https://developers.google.com/translate/v2/faq#pricing].

For more information about Google Translate API visit https://developers.google.com/translate/

## Example

Follow example will translate whole content of members-cs_CZ.po from Wnglish to Czech language (default)

    potrans -k 123456789 -i members-cs_CZ.po -v

Another example it's about output.

    potrans -k 123456789 -i example.po -v

## Help

Just run: `potrans --help`
