<?php

namespace App\Services\Finance;

use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paystack, called from the server only. A payment is confirmed after
 * Paystack's verify endpoint says so (or a signed webhook arrives) —
 * never because the customer came back to a "success" page.
 */
class PaystackGateway
{
    protected string $base = 'https://api.paystack.co';

    public function __construct(protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    /** Keys and mode: saved in Settings → Payments, or from .env. */
    public function config(): array
    {
        return $this->settings->paystack();
    }

    public function enabled(): bool
    {
        return (bool) ($this->config()['active'] ?? false) && $this->secret() !== '';
    }

    public function mode(): string
    {
        return ($this->config()['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public function secret(): string
    {
        $c = $this->config();
        $key = ($c['mode'] ?? 'sandbox') === 'live'
            ? (($c['live_secret_key'] ?? null) ?: ($c['secret_key'] ?? null))
            : (($c['test_secret_key'] ?? null) ?: ($c['secret_key'] ?? null));

        return (string) $key;
    }

    /** Start a transaction; returns the page to send the customer to. */
    public function initialize(string $email, int $amountMinor, string $currency, string $reference, string $callbackUrl, array $metadata = []): string
    {
        $res = Http::withToken($this->secret())->acceptJson()->timeout(20)
            ->post($this->base . '/transaction/initialize', [
                'email'        => $email,
                'amount'       => $amountMinor,
                'currency'     => $currency,
                'reference'    => $reference,
                'callback_url' => $callbackUrl,
                'metadata'     => $metadata,
            ]);

        if (!$res->successful() || !$res->json('status') || !$res->json('data.authorization_url')) {
            throw new RuntimeException('Paystack could not start the payment: ' . ($res->json('message') ?? 'HTTP ' . $res->status()));
        }

        return $res->json('data.authorization_url');
    }

    /** Ask Paystack what happened to a transaction. Returns Paystack's `data` object. */
    public function verify(string $reference): array
    {
        $res = Http::withToken($this->secret())->acceptJson()->timeout(20)
            ->get($this->base . '/transaction/verify/' . rawurlencode($reference));

        if (!$res->successful() || !$res->json('status')) {
            throw new RuntimeException('Paystack could not verify the payment: ' . ($res->json('message') ?? 'HTTP ' . $res->status()));
        }

        return (array) $res->json('data');
    }

    public function validSignature(string $payload, ?string $signature): bool
    {
        return $signature !== null && $this->secret() !== ''
            && hash_equals(hash_hmac('sha512', $payload, $this->secret()), $signature);
    }

    /** Check a secret key with Paystack. Returns null when it works, or the reason it doesn't. */
    public function check(?string $secret = null): ?string
    {
        $secret ??= $this->secret();
        if ($secret === '') return 'No secret key is set.';
        try {
            $res = Http::withToken($secret)->acceptJson()->timeout(15)->get($this->base . '/balance');
        } catch (\Throwable $e) {
            return 'Could not reach Paystack: ' . $e->getMessage();
        }
        if ($res->successful() && $res->json('status')) return null;

        return $res->json('message') ?: 'Paystack answered HTTP ' . $res->status() . '.';
    }
}
