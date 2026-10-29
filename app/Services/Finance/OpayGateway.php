<?php

namespace App\Services\Finance;

use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OPay Checkout (cashier), called from the server only. Like Paystack, a
 * payment is confirmed only after OPay's status endpoint says SUCCESS for the
 * exact amount; the return page and the callback just prompt that check.
 *
 *  - Create:  POST /api/v1/international/cashier/create, signed with the public key.
 *  - Status:  POST /api/v1/international/cashier/status, signed with an HMAC-SHA512
 *             of the request body using the secret key.
 */
class OpayGateway
{
    public const BASE = ['sandbox' => 'https://testapi.opaycheckout.com', 'live' => 'https://liveapi.opaycheckout.com'];

    public function __construct(protected ?Settings $settings = null)
    {
        $this->settings ??= app(Settings::class);
    }

    /** Keys and mode: saved in Settings → Payments, or from .env. */
    public function config(): array
    {
        return $this->settings->opay();
    }

    public function enabled(): bool
    {
        return (bool) ($this->config()['active'] ?? false)
            && $this->merchantId() !== '' && $this->publicKey() !== '' && $this->secret() !== '';
    }

    public function mode(): string
    {
        return ($this->config()['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    public function merchantId(): string
    {
        return trim((string) ($this->config()['merchant_id'] ?? ''));
    }

    public function publicKey(): string
    {
        return (string) ($this->config()[$this->mode() === 'live' ? 'live_public_key' : 'test_public_key'] ?? '');
    }

    public function secret(): string
    {
        return (string) ($this->config()[$this->mode() === 'live' ? 'live_secret_key' : 'test_secret_key'] ?? '');
    }

    /** Start a payment; returns OPay's cashier page to send the payer to. */
    public function initialize(array $payer, int $amountMinor, string $currency, string $reference, string $returnUrl, string $callbackUrl, string $cancelUrl, string $product): string
    {
        $body = [
            'country'     => 'NG',
            'reference'   => $reference,
            'amount'      => ['total' => $amountMinor, 'currency' => $currency],
            'returnUrl'   => $returnUrl,
            'callbackUrl' => $callbackUrl,
            'cancelUrl'   => $cancelUrl,
            'expireAt'    => 30,
            'userInfo'    => array_filter([
                'userEmail'  => $payer['email'] ?? null,
                'userId'     => isset($payer['id']) ? (string) $payer['id'] : null,
                'userName'   => $payer['name'] ?? null,
                'userMobile' => $payer['phone'] ?? null,
            ]),
            'product'     => ['name' => mb_substr($product, 0, 100), 'description' => mb_substr($product, 0, 200)],
        ];

        $res = Http::withToken($this->publicKey())->withHeaders(['MerchantId' => $this->merchantId()])
            ->acceptJson()->timeout(20)->post($this->base() . '/api/v1/international/cashier/create', $body);

        if (!$res->successful() || $res->json('code') !== '00000' || !$res->json('data.cashierUrl')) {
            throw new RuntimeException('OPay could not start the payment: ' . ($res->json('message') ?? 'HTTP ' . $res->status()));
        }

        return $res->json('data.cashierUrl');
    }

    /** Ask OPay what happened to a payment. Returns OPay's `data` object (status, amount{total, currency}, …). */
    public function status(string $reference): array
    {
        $res = $this->signedPost('/api/v1/international/cashier/status', ['country' => 'NG', 'reference' => $reference]);

        if (!$res->successful() || $res->json('code') !== '00000') {
            throw new RuntimeException('OPay could not check the payment: ' . ($res->json('message') ?? 'HTTP ' . $res->status()));
        }

        return (array) $res->json('data');
    }

    /** Check the keys with OPay. Returns null when they work, or the reason they don't. */
    public function check(): ?string
    {
        if ($this->merchantId() === '') return 'No merchant ID is set.';
        if ($this->secret() === '') return 'No secret key is set.';
        try {
            // Ask about a payment that doesn't exist: good keys get "not found", bad keys an authentication error.
            $res = $this->signedPost('/api/v1/international/cashier/status', ['country' => 'NG', 'reference' => 'QH-KEYCHECK-' . now()->timestamp]);
        } catch (\Throwable $e) {
            return 'Could not reach OPay: ' . $e->getMessage();
        }
        $code = (string) $res->json('code');
        $message = (string) $res->json('message');
        if ($res->successful() && ($code === '00000' || preg_match('/not\s*(exist|found)/i', $message))) return null;

        return $message !== '' ? $message . ($code !== '' ? " (code {$code})" : '') : 'OPay answered HTTP ' . $res->status() . '.';
    }

    protected function signedPost(string $path, array $body): \Illuminate\Http\Client\Response
    {
        // The signature covers the exact bytes sent, so send the encoded string itself.
        $json = json_encode($body, JSON_UNESCAPED_SLASHES);

        return Http::withToken(hash_hmac('sha512', $json, $this->secret()))
            ->withHeaders(['MerchantId' => $this->merchantId()])
            ->acceptJson()->timeout(20)
            ->withBody($json, 'application/json')
            ->post($this->base() . $path);
    }

    protected function base(): string
    {
        return self::BASE[$this->mode()];
    }
}
