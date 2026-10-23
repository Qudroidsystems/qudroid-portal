<?php

namespace App\Services\Mail;

use App\Models\Mail\MailAccount;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Opens mailboxes and sends mail for Qmail accounts. Tests call fake(): mail then
 * lives in FakeGateway and sending delivers to any fake mailbox it is addressed to.
 */
class Mailboxes
{
    protected static bool $fake = false;

    /** @var array<int, array{from: string, to: string[], subject: string, raw: string}> */
    public static array $sent = [];

    public static function fake(): void
    {
        self::$fake = true;
        self::$sent = [];
        FakeGateway::reset();
    }

    public static function isFake(): bool
    {
        return self::$fake;
    }

    public static function connect(MailAccount $account): Gateway
    {
        return self::$fake ? new FakeGateway($account->email) : new ImapGateway($account);
    }

    /** Send through the account's SMTP server. Returns the raw message as sent. */
    public static function send(MailAccount $account, Email $email): string
    {
        $rcpt = array_map(fn (Address $a) => $a, [...$email->getTo(), ...$email->getCc(), ...$email->getBcc()]);
        $raw = $email->toString();
        if (self::$fake) {
            self::$sent[] = ['from' => $account->email, 'to' => array_map(fn ($a) => $a->getAddress(), $rcpt), 'subject' => (string) $email->getSubject(), 'raw' => $raw];
            foreach ($rcpt as $a) {
                if (isset(FakeGateway::$boxes[strtolower($a->getAddress())])) FakeGateway::deliver(strtolower($a->getAddress()), self::withoutBcc($raw));
            }

            return $raw;
        }

        $transport = new EsmtpTransport($account->smtp_host, (int) $account->smtp_port, $account->smtp_encryption === 'ssl');
        if ($account->smtp_encryption === 'none') $transport->setAutoTls(false);
        if ($account->smtp_encryption === 'tls') $transport->setRequireTls(true);
        if (!config('qudroid.mail.validate_cert', true)) {
            $transport->getStream()->setStreamOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        }
        $transport->setUsername($account->username);
        $transport->setPassword($account->password);
        $transport->send($email, new Envelope(new Address($account->email), $rcpt));

        return $raw;
    }

    /** Bcc is in the envelope only; the copy kept in Sent keeps it, delivered copies don't. */
    public static function withoutBcc(string $raw): string
    {
        return (string) preg_replace('/^Bcc:.*(\r?\n[ \t].*)*\r?\n/mi', '', $raw, 1);
    }

    /** Check the settings work: log in to IMAP, and connect and log in to SMTP. Returns an error message or null. */
    public static function test(MailAccount $account): ?string
    {
        if (self::$fake) return null;
        try {
            $g = new ImapGateway($account);
            $g->folders();
            $g->close();
        } catch (\Throwable $e) {
            return 'Incoming (IMAP): ' . self::friendly($e);
        }
        try {
            $t = new EsmtpTransport($account->smtp_host, (int) $account->smtp_port, $account->smtp_encryption === 'ssl');
            if ($account->smtp_encryption === 'none') $t->setAutoTls(false);
            $t->setUsername($account->username);
            $t->setPassword($account->password);
            $t->start();
            $t->stop();
        } catch (\Throwable $e) {
            return 'Outgoing (SMTP): ' . self::friendly($e);
        }

        return null;
    }

    public static function friendly(\Throwable $e): string
    {
        $m = $e->getMessage();
        if (stripos($m, 'auth') !== false || stripos($m, 'login') !== false || stripos($m, 'credentials') !== false) return 'the username or password was not accepted.';
        if (stripos($m, 'connection') !== false || stripos($m, 'timed out') !== false || stripos($m, 'refused') !== false) return 'could not reach the server. Check the host, port and encryption.';

        return mb_strimwidth($m, 0, 200, '…');
    }
}
