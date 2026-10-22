<?php

namespace App\Services\Finance;

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

    public function enabled(): bool
    {
        return (bool) config('services.paystack.active') && $this->secret() !== '';
    }

    public function secret(): string
    {
        $c = config('services.paystack');
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
}
