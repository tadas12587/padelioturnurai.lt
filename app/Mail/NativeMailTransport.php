<?php

namespace App\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transport that uses PHP's built-in mail() function.
 * Works on shared hosting where proc_open() is disabled.
 */
class NativeMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        // Build To header
        $toAddresses = array_map(fn ($a) => $a->getAddress(), $email->getTo());
        $to = implode(', ', $toAddresses);

        $subject = $email->getSubject() ?? '(no subject)';

        // Prefer HTML body
        $body = $email->getHtmlBody() ?? $email->getTextBody() ?? '';
        $isHtml = $email->getHtmlBody() !== null;

        // Build additional headers
        $extraHeaders = [];

        if ($froms = $email->getFrom()) {
            $f = $froms[0];
            $fromStr = $f->getName()
                ? '"' . addslashes($f->getName()) . '" <' . $f->getAddress() . '>'
                : $f->getAddress();
            $extraHeaders[] = 'From: ' . $fromStr;
        }

        $extraHeaders[] = 'MIME-Version: 1.0';
        $extraHeaders[] = $isHtml
            ? 'Content-Type: text/html; charset=UTF-8'
            : 'Content-Type: text/plain; charset=UTF-8';
        $extraHeaders[] = 'Content-Transfer-Encoding: 8bit';
        $extraHeaders[] = 'X-Mailer: PHP/' . PHP_VERSION;

        // Reply-To
        if ($replyTos = $email->getReplyTo()) {
            $rt = $replyTos[0];
            $extraHeaders[] = 'Reply-To: ' . $rt->getAddress();
        }

        $headerString = implode("\r\n", $extraHeaders);

        if (! @mail($to, $subject, $body, $headerString)) {
            throw new \RuntimeException(
                'PHP mail() failed. Check server mail configuration.'
            );
        }
    }

    public function __toString(): string
    {
        return 'phpmail://default';
    }
}
