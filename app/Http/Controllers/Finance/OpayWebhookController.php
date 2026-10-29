<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OPay → POST /webhook/opay (sent as the callbackUrl of each payment, so
 * nothing needs setting in the OPay dashboard). Nothing in the body is
 * trusted: it only names a reference, which is then checked with OPay's
 * status API using our secret key before anything is confirmed.
 */
class OpayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments)
    {
        $reference = (string) ($request->input('payload.reference') ?? $request->input('reference') ?? '');
        if ($reference === '' || !str_starts_with($reference, 'QH-')) {
            return response()->json(['ok' => true]);
        }

        try {
            $payments->settleOpay($reference);
        } catch (\Throwable $e) {
            Log::error('OPay callback could not settle payment', ['reference' => $reference, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false], 500); // OPay retries failed callbacks
        }

        return response()->json(['ok' => true]);
    }
}
