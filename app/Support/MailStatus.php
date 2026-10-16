<?php

namespace App\Support;

/** Plain-language view of the outgoing email setup, for admins. */
class MailStatus
{
    public static function summary(): array
    {
        $mailer = (string) config('mail.default');
        $smtp = config('mail.mailers.smtp');

        return [
            'configured' => ! in_array($mailer, ['log', 'array'], true),
            'provider' => $mailer === 'smtp'
                ? (str_contains((string) $smtp['host'], 'gmail.com') ? 'Google (Gmail SMTP)' : 'SMTP · '.$smtp['host'])
                : ucfirst($mailer),
            'from' => trim(config('mail.from.name').' <'.config('mail.from.address').'>'),
            'verify' => (bool) config('taskflow.verify_email'),
        ];
    }

    /** Turn a mailer exception into something an admin can act on. */
    public static function explain(\Throwable $e): string
    {
        $msg = $e->getMessage();
        $smtp = config('mail.mailers.smtp');
        $where = $smtp['host'].':'.$smtp['port'];

        return match (true) {
            (bool) preg_match('/\b535\b|Username and Password not accepted|BadCredentials|authenticat/i', $msg)
                => 'Google rejected the sign-in. Check that MAIL_USERNAME is the full Google address and MAIL_PASSWORD is an app password (not the account password).',
            (bool) preg_match('/timed out|Connection refused|Network is unreachable|getaddrinfo|Unable to connect|Connection could not be established/i', $msg)
                => "Couldn’t reach {$where}. On Railway, outgoing email only works on the Pro plan; on other hosts, check that the port isn’t blocked.",
            (bool) preg_match('/\b55[0-4]\b|not allowed to send|sender address|SendAsDenied|Mail From/i', $msg)
                => 'The mail server refused the sender. MAIL_FROM_ADDRESS must be the Google account’s own address or one of its aliases.',
            default => 'Sending failed: '.\Illuminate\Support\Str::limit($msg, 200),
        };
    }
}
