<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free document numbers: QUD-INV-2026-0001, QUD-RCT-2026-0001.
 * Must be called inside the database transaction that saves the document,
 * so a rolled-back save also gives the number back.
 */
class DocumentNumber
{
    public const PREFIXES = ['invoice' => 'INV', 'receipt' => 'RCT', 'certificate' => 'CERT', 'journal' => 'JRN', 'expense' => 'EXP', 'purchase_order' => 'PO'];

    public static function next(string $key): string
    {
        $year = (int) now()->year;
        $row = DB::table('finance_sequences')->where('key', $key)->where('year', $year)->lockForUpdate()->first();

        if ($row) {
            $n = $row->last + 1;
            DB::table('finance_sequences')->where('key', $key)->where('year', $year)->update(['last' => $n]);
        } else {
            $n = 1;
            DB::table('finance_sequences')->insert(['key' => $key, 'year' => $year, 'last' => $n]);
        }

        $prefix = config('qudroid.finance.number_prefix', 'QUD');

        return $prefix . '-' . (self::PREFIXES[$key] ?? strtoupper($key)) . '-' . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
