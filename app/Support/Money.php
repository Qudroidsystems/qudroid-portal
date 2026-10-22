<?php

namespace App\Support;

/** Display helper for amounts. Arithmetic on money happens in the database or in kobo, never here. */
class Money
{
    public const SYMBOLS = ['NGN' => '₦', 'USD' => '$', 'GBP' => '£', 'EUR' => '€'];

    public static function format($amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: config('qudroid.academy.currency', 'NGN'));
        $symbol = self::SYMBOLS[$currency] ?? $currency . ' ';

        return $symbol . number_format((float) $amount, 2);
    }
}
