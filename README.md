
# ewwink/EmailMimeParser
Portable PHP Class to parse Raw email (.eml) files or Mime Messages. 

For maximum PHP version compatibility, this Class does not use `iconv` or the `IMAP` extension, but instead uses `mbstring` (`mb_convert_encoding`) to handle character encoding.

# Usage
To use this MIME Parser pass the string to the class, for example if you have this `$rawText`:

```text
From: Github <github@example.com>
Date: Wed, 3 Dec 2025 12:57:21 +0700
Subject: =?UTF-8?B?TWFkZSB3aXRoIOKdpA==?=
To: user@example.com
Content-Type: multipart/alternative; boundary="0000000000005bdbea064505e33b"

--0000000000005bdbea064505e33b
Content-Type: text/plain; charset="UTF-8"

This is plain text message

--0000000000005bdbea064505e33b
Content-Type: text/html; charset="UTF-8"

<div style="font-color:blue">This is html message</div>

--0000000000005bdbea064505e33b--
```

then

```php
<?php
require "EmailMimeParser.php";

$rawText = file_get_contents("file.eml");
$parsed = new EmailMimeParser($rawText);

echo $parsed->to; # user@example.com
echo $parsed->from; # Github <github@example.com>
echo $parsed->date; # Wed, 3 Dec 2025 12:57:21 +0700
echo $parsed->subject; # Made with ❤

echo $parsed->html; # <div style="font-color:blue">This is html message</div>
echo $parsed->text; # This is plain text message
echo $parsed->attachments; # Array of File attachments

echo $parsed->body(); # Get the html is exist, else return text: <div style="font-color:blue">This is html message</div>
echo $parsed->body('text'); # Get the text if exist, else return the html: This is plain text message

print_r($parsed->all()); # get the email head, body and attachments

/*
Array
(
    [head] => Array
        (
            [to] => user@example.com
            [from] => Github <github@example.com>
            [subject] => Made with ❤
            [date] => Wed, 3 Dec 2025 20:40:18 +0000
        )

    [body] => Array
        (
            [html] => <div style="font-color:blue">This is html message</div>
            [text] => This is plain text message
        )

    [attachments] => Array
        (
            [file-name] => file-content
        )
)
*/
```

