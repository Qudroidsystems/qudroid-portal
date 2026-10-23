<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates and removes mailboxes on a Stalwart mail server through its management API,
 * so admins can hand out @company addresses from the portal. Optional: any IMAP/SMTP
 * server works for connecting existing mailboxes.
 */
class Stalwart
{
    public static function configured(): bool
    {
        $s = MailService::server();

        return !empty($s['stalwart_url']) && !empty($s['stalwart_token']);
    }

    protected function http()
    {
        $s = MailService::server();

        return Http::baseUrl(rtrim((string) $s['stalwart_url'], '/') . '/api')->withToken((string) $s['stalwart_token'])
            ->acceptJson()->timeout(15)->withOptions(['verify' => (bool) config('qudroid.mail.validate_cert', true)]);
    }

    /** The account name on the server: the part before the @. */
    public static function login(string $email): string
    {
        return Str::lower($email);
    }

    public function create(string $email, ?string $name, string $password, int $quotaMb = 0): void
    {
        $r = $this->http()->post('/principal', array_filter([
            'type' => 'individual', 'name' => self::login($email), 'secrets' => [$password], 'emails' => [Str::lower($email)],
            'description' => $name, 'quota' => $quotaMb > 0 ? $quotaMb * 1048576 : null, 'roles' => ['user'],
        ], fn ($v) => $v !== null));
        $this->check($r, 'create the mailbox');
    }

    public function setPassword(string $email, string $password): void
    {
        $r = $this->http()->patch('/principal/' . rawurlencode(self::login($email)), [['action' => 'set', 'field' => 'secrets', 'value' => [$password]]]);
        $this->check($r, 'change the password');
    }

    public function delete(string $email): void
    {
        $r = $this->http()->delete('/principal/' . rawurlencode(self::login($email)));
        if ($r->status() !== 404) $this->check($r, 'delete the mailbox');
    }

    protected function check($r, string $what): void
    {
        $body = $r->json();
        if ($r->successful() && !isset($body['error'])) return;
        $why = $body['details'] ?? $body['error'] ?? ('HTTP ' . $r->status());
        if (($body['error'] ?? '') === 'fieldAlreadyExists') $why = 'that address is already taken on the mail server';
        throw ValidationException::withMessages(['email' => 'The mail server could not ' . $what . ': ' . (is_string($why) ? $why : json_encode($why)) . '.']);
    }

    /** A strong password the person never has to type (the portal signs in for them). */
    public static function password(): string
    {
        return Str::password(24, symbols: false);
    }
}
