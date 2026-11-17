{{-- A payment receipt. The PDF is attached; the button downloads it again. --}}
@php($money = fn ($v) => \App\Support\Money::format($v, $payment->currency))
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Receipt {{ $payment->number }}</title></head>
<body style="margin:0;padding:24px;background:#f3f6f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#212529">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;padding:28px">
        <div style="font-weight:600;font-size:15px;color:#405189;margin-bottom:18px">{{ config('qudroid.company', config('app.name')) }}</div>
        <p style="margin:0 0 14px">Hello {{ \Illuminate\Support\Str::of((string) $payment->user?->name)->before(' ') ?: 'there' }},</p>
        <p style="margin:0 0 14px;line-height:1.5">Thank you. We have received your payment of <b>{{ $money($payment->amount) }}</b>. Your receipt is attached.</p>
        <table role="presentation" style="width:100%;border-collapse:collapse;margin:6px 0 18px;font-size:14px">
            <tr><td style="padding:8px 10px;border-bottom:1px solid #eef0f3;color:#6c757d">Receipt</td><td style="padding:8px 10px;border-bottom:1px solid #eef0f3;text-align:right;font-weight:600">{{ $payment->number }}</td></tr>
            <tr><td style="padding:8px 10px;border-bottom:1px solid #eef0f3;color:#6c757d">Date</td><td style="padding:8px 10px;border-bottom:1px solid #eef0f3;text-align:right">{{ ($payment->paid_at ?? $payment->confirmed_at)?->format('j M Y') }}</td></tr>
            <tr><td style="padding:8px 10px;border-bottom:1px solid #eef0f3;color:#6c757d">Paid by</td><td style="padding:8px 10px;border-bottom:1px solid #eef0f3;text-align:right">{{ \App\Models\Finance\Payment::METHODS[$payment->method] ?? ucfirst((string) $payment->method) }}</td></tr>
            @foreach($payment->allocations as $a)
                <tr><td style="padding:8px 10px;border-bottom:1px solid #eef0f3;color:#6c757d">{{ $a->invoice?->number }}<div style="font-size:12px">{{ $a->invoice?->title }}</div></td>
                    <td style="padding:8px 10px;border-bottom:1px solid #eef0f3;text-align:right">{{ $money($a->amount) }}@if($a->invoice && $a->invoice->balanceMinor() > 0)<div style="font-size:12px;color:#6c757d">balance {{ $money($a->invoice->balance()) }}</div>@endif</td></tr>
            @endforeach
        </table>
        <p style="margin:18px 0;text-align:center"><a href="{{ $downloadUrl }}" style="display:inline-block;background:#405189;color:#fff;text-decoration:none;padding:11px 22px;border-radius:6px;font-weight:600">Download receipt (PDF)</a></p>
    </div>
    <p style="text-align:center;font-size:12px;color:#878a99;margin-top:14px">Sent by {{ config('qudroid.company', config('app.name')) }}. Keep this e-mail for your records.</p>
</body>
</html>
