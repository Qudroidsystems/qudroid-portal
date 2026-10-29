@extends('site.layout')
@section('title', 'Pay ' . $invoice->number)
@php($money = fn ($v) => \App\Services\Website\Content::money($v, $invoice->currency))

@section('content')
    <section class="s-section is-tight">
        <div class="s-wrap">
            <div class="s-pay">
                <h1 class="s-h2" style="text-align:center">Checkout.</h1>
                @if($invoice->status === 'paid' || $invoice->balanceMinor() <= 0)
                    <div class="s-alert is-ok">This invoice is fully paid. Thank you!</div>
                @else
                    <div class="s-pay-box" style="margin-top:24px">
                        <dl class="s-dl">
                            <dt>Invoice</dt><dd>{{ $invoice->number }}</dd>
                            <dt>Billed to</dt><dd>{{ $invoice->user?->name }}</dd>
                            @foreach($invoice->items as $item)<dt>{{ $loop->first ? 'Items' : '' }}</dt><dd>{{ $item->description }} · {{ $money($item->amount) }}</dd>@endforeach
                            @if((float) $invoice->amount_paid > 0)<dt>Already paid</dt><dd>{{ $money($invoice->amount_paid) }}</dd>@endif
                        </dl>
                        <div class="s-pay-total"><span>Balance</span><b>{{ $money($invoice->balance()) }}</b></div>

                        @if($errors->any())<div class="s-alert is-bad" style="margin:12px 0 0">{{ $errors->first() }}</div>@endif

                        @if(empty($gateways))
                            <div class="s-alert is-info" style="margin:16px 0 0">Online payment is not available right now. Please contact us to pay by bank transfer, quoting {{ $invoice->number }}.</div>
                        @else
                            <form method="POST" action="{{ $action }}" class="s-form" style="margin-top:16px" data-no-loader>
                                @csrf
                                <div class="s-field">
                                    <label for="p-amount">Amount to pay ({{ $invoice->currency }})</label>
                                    <input id="p-amount" class="s-input" type="number" name="amount" step="0.01" min="{{ $minimum }}" max="{{ $invoice->balance() }}" value="{{ old('amount', $invoice->balance()) }}" required>
                                    @if((float) $minimum < (float) $invoice->balance())<div class="s-muted" style="font-size:13px;margin-top:6px">You can pay part now: at least {{ $money($minimum) }}.</div>@endif
                                </div>
                                <div class="s-gateways">
                                    @foreach($gateways as $key => $label)
                                        <button class="s-btn {{ $loop->first ? '' : 'is-ghost' }}" name="gateway" value="{{ $key }}"><i class="ri-lock-line"></i> Pay with {{ $label }}</button>
                                    @endforeach
                                </div>
                                <p class="s-muted" style="font-size:13px;margin:14px 0 0;text-align:center">You will finish paying on the provider’s secure page.</p>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
