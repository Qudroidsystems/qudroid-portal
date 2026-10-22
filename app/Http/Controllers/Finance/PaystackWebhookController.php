<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\PaymentService;
use App\Services\Finance\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paystack → POST /webhook/paystack. Set this URL in the Paystack dashboard.
 * The signature is checked, and the payment is still verified with Paystack's
 * API before it is confirmed.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackGateway $paystack, PaymentService $payments)
    {
        if (!$paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response('Invalid signature', 401);
        }

        if ($request->input('event') === 'charge.success' && ($reference = $request->input('data.reference'))) {
            try {
                $payments->settlePaystack((string) $reference);
            } catch (\Throwable $e) {
                Log::error('Paystack webhook could not settle payment', ['reference' => $reference, 'error' => $e->getMessage()]);

                return response('Retry later', 500); // Paystack retries failed webhooks
            }
        }

        return response('OK', 200);
    }
}
