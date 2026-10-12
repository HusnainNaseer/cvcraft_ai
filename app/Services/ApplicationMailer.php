<?php

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends a job application email with the tailored CV attached.
 *
 * Uses the user's connected Gmail account when available, otherwise the app's SMTP mailer.
 */
class ApplicationMailer
{
    public const VIA_GMAIL = 'gmail';

    public const VIA_SMTP = 'smtp';

    public function __construct(private readonly GmailClient $gmail)
    {
    }

    public function smtpAvailable(): bool
    {
        return config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.username'));
    }

    /**
     * @param  array|null  $gmailToken  session token from GmailClient, or null to use SMTP
     * @param  array{to: string, subject: string, body: string, replyTo: ?string, senderName: string}  $message
     * @param  array{name: string, data: string, mime: string}  $attachment
     * @return array{via: string, from: string, token: ?array}
     */
    public function send(?array $gmailToken, array $message, array $attachment): array
    {
        if ($gmailToken) {
            $token = $this->gmail->freshToken($gmailToken);

            $email = (new Email())
                ->from(new Address($token['email'], $message['senderName'] ?: ($token['name'] ?? '')))
                ->to($message['to'])
                ->subject($message['subject'])
                ->text($message['body'])
                ->attach($attachment['data'], $attachment['name'], $attachment['mime']);

            $this->gmail->send($token, $email);

            return ['via' => self::VIA_GMAIL, 'from' => $token['email'], 'token' => $token];
        }

        if (! $this->smtpAvailable()) {
            throw new RuntimeException('Connect your Gmail account to send applications.');
        }

        Mail::raw($message['body'], function (Message $mail) use ($message, $attachment) {
            $mail->to($message['to'])
                ->subject($message['subject'])
                ->attachData($attachment['data'], $attachment['name'], ['mime' => $attachment['mime']]);

            if ($message['replyTo']) {
                $mail->replyTo($message['replyTo'], $message['senderName']);
            }
        });

        return ['via' => self::VIA_SMTP, 'from' => (string) config('mail.from.address'), 'token' => null];
    }
}
