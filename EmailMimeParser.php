<?php

/**
 * Portable PHP Class for parsing raw email (EML) or MIME messages, PHP ICONV and IMAP extension is not required.
 * 
 * @author ewwink
 * @version 1.0
 * @link https://github.com/ewwink/EmailMimeParser
 * @license GPL
 * 
 */

class EmailMimeParser {
    public $to = "";
    public $from = "";
    public $subject = "";
    public $date = "";
    public $html = "";
    public $text = "";
    public $attachments = [];
    public $minifyHtml = [];
    private $emlText = "";
    private $emailBody = '';
    private $emailHead = '';

    public function __construct($emlText, $minifyHtml = true) {
        $this->minifyHtml = $minifyHtml;
        $this->emlText = $emlText;
        $this->processText();
    }

    private function processText() {
        $this->emlText = str_replace("\r\n", "\n", $this->emlText);
        list($head, $body) = explode("\n\n", $this->emlText, 2);
        $this->emailBody = trim($body);
        $this->emailHead = trim($head);
        $this->parseHeaders();
        $this->parseBody();
    }

    private function parseHeaders() {
        preg_match_all('#^(from|to|date):\s+(.+)#mi', $this->emailHead, $headers, PREG_SET_ORDER);
        foreach ($headers as $head)
            $this->{strtolower($head[1])} = $head[2];

        $this->decodeSubject();
    }

    private function decodeSubject() {
        preg_match('/^Subject:\s*(.*(?:\n\s+.*)*)/mi', $this->emailHead, $subject);
        if (count($subject) < 2) {
            $this->subject = '-error- Unknown Subject';
            return;
        }
        $subject = trim($subject[1]);
        $decodedSubject = "";
        if (preg_match_all('/=\?([^\?]+)\?([B|Q])\?([^\?]+)\?=/i', $subject, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                [$_m, $charset, $encoding, $str] = $match;
                if ($this->contains($encoding, 'B'))
                    $decoded = base64_decode($str);
                elseif ($this->contains($encoding, 'Q')) {
                    $decoded = quoted_printable_decode($str);
                    $decoded = str_replace('_', ' ', $decoded);
                }
                $decodedSubject .= mb_convert_encoding($decoded, 'UTF-8', $charset);
            }
            $this->subject = $decodedSubject;
        } else {
            $this->subject = $subject;
        }
    }

    private function parseBody() {;
        if ($boundary = $this->getBoundary($this->emailHead)) {
            $this->parseParts($boundary, $this->emailBody);
        } else {
            preg_match_all('/^(Content-Type|Content-Transfer-Encoding):\s+(.+)/mi', $this->emailHead, $headers, PREG_SET_ORDER);
            $contentType = $headers[0][2] ?? "text/plain";
            $contentTransferEncoding = $headers[1][2] ?? "";
            $content = [
                "Content-Type: {$contentType}\nContent-Transfer-Encoding: {$contentTransferEncoding}",
                $this->emailBody
            ];
            $this->decodeContent($content);
        }
    }

    private function getBoundary($body) {
        $boundary = '';
        if (preg_match('/boundary="([^"]+)"/', $body, $boundary)) {
            $boundary = $boundary[1];
        } else if (preg_match('/boundary=([\w-]+)/', $body, $boundary)) {
            $boundary = $boundary[1];
        }
        return $boundary;
    }

    private function parseParts($boundary, $part) {
        $messages = preg_split("#--{$boundary}(--)?#", $part);
        foreach ($messages as $msg) {
            if (!$msg = trim($msg))
                continue;
            $emailBody = explode("\n\n", $msg, 2);
            if ($this->contains($msg, 'multipart/')) {
                if ($boundary = $this->getBoundary($emailBody[0]))
                    $this->parseParts($boundary, $emailBody[1]);
                else {
                    echo "fff";
                }
            } else {
                $this->decodeContent($emailBody);
            }
        }
    }

    private function decodeContent($content) {
        if (count($content) < 2)
            return;
        list($msgHead, $msgBody) = $content;
        if (preg_match('#Content-Transfer-Encoding:\s+?(.+)#i', $msgHead, $encoding)) {
            if ($encoding[1] == 'base64')
                $msgBody = base64_decode($msgBody);
            elseif ($encoding[1] == 'quoted-printable')
                $msgBody = quoted_printable_decode($msgBody);

            if (preg_match('#charset="?([\w-]+)#', $msgHead, $charset)) {
                $charset = strtoupper($charset[1]);
                if (!in_array($charset, ['UTF-8', 'ISO-8859-1', 'US-ASCII'])) {
                    try {
                        if ($decoded = @mb_convert_encoding($msgBody, 'UTF-8', $charset))
                            $msgBody = $decoded;
                    } catch (ValueError $e) {
                    }
                }
            }
        }

        if ($this->contains($msgHead, 'text/plain')) {
            $this->text = $msgBody;
        } elseif ($this->contains($msgHead, 'text/html')) {
            $this->html = $this->minifyHtml($msgBody);
        } elseif ($this->contains($msgHead, 'Content-Disposition: attachment')) {
            $filename = preg_match('#filename="([^"]+)"#', $msgHead, $filename) ? $filename[1] : "attachment-" . time();
            $this->attachments[$filename] = $msgBody;
        }
    }

    private function contains($haystack, $needle, $case_insensitive = true) {
        if ($case_insensitive)
            return stripos($haystack, $needle) !== false;
        return strpos($haystack, $needle) !== false;
    }

    public function minifyHtml($html) {
        $html = preg_replace('#\s+#m', ' ', $html);
        $html = str_replace(['> <', '> ', ' <'], ['><', '>', '<'], $html);
        return $html;
    }

    /** 
     * Prioritize displaying email body "html" or "text" as the first option.
     */
    public function body($type = "html") {
        if ($type == "html")
            return $this->html ? $this->html : $this->text;
        else
            return $this->text ? $this->text : $this->html;
    }
    /**
     * @return array of head and body
     */
    public function all() {
        return [
            'head' => [
                'to' => $this->to,
                'from' => $this->from,
                'subject' => $this->subject,
                'date' => $this->date
            ],
            'body' => [
                'html' => $this->html,
                'text' => $this->text
            ],
            'attachments' => $this->attachments
        ];
    }
}
