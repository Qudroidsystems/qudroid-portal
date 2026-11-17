{{--
    Course fees for one student, with what staff can do to help them pay:
    record money received, pay online for them now, send a link to pay, re-send a receipt.
    Needs: $invoices (course-fee invoices, with allocations.payment), $gateways.
--}}
@php $money = fn ($v, $c) => \App\Support\Money::format($v, $c); @endphp
<x-cb.card title="Fees and payments" icon="ri-bank-card-line" :count="$invoices->count()" :flush="true" id="fees">
    @if($invoices->isEmpty())
        <div class="empty-state"><i class="ri-bank-card-line"></i><h6>No fees yet</h6><p>An invoice is issued when the student is enrolled on a class with a fee.</p></div>
    @else
        <div class="list-group list-group-flush">
        @foreach($invoices as $inv)
            @php $open = $inv->isOpen() && $inv->balanceMinor() > 0; $payments = $inv->allocations->pluck('payment')->filter(fn ($p) => $p && $p->status === 'confirmed')->unique('id'); @endphp
            <div class="list-group-item py-3">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">@can('View finance')<a href="{{ route('finance.invoices.show', $inv) }}">{{ $inv->number }}</a>@else{{ $inv->number }}@endcan · {{ $inv->title }}</div>
                        <div class="small text-muted">@foreach($inv->items as $it){{ $it->description }}@if(!$loop->last); @endif @endforeach</div>
                        <div class="small text-muted">Issued {{ $inv->issue_date?->format('j M Y') }}@if($inv->due_date) · due {{ $inv->due_date->format('j M Y') }}@endif</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-semibold">{{ $money($inv->total, $inv->currency) }}</div>
                        <div class="small">@include('modules.academy.partials.pill', ['status' => $inv->status === 'paid' ? 'completed' : ($inv->isOverdue() ? 'cancelled' : 'pending'), 'label' => $inv->status_label])</div>
                        @if($open)<div class="small text-muted">Balance {{ $money($inv->balance(), $inv->currency) }}</div>@endif
                    </div>
                </div>

                @if($payments->isNotEmpty())
                    <div class="mt-2">
                        @foreach($payments as $p)
                            <div class="d-flex flex-wrap align-items-center gap-2 small py-1 border-top">
                                <i class="ri-checkbox-circle-line text-success"></i>
                                <span>{{ $p->number }} · {{ $money($p->amount, $p->currency) }} · {{ $p->method_label }} · {{ ($p->paid_at ?? $p->confirmed_at)?->format('j M Y') }}</span>
                                <span class="ms-auto d-flex gap-1">
                                    <a class="btn btn-sm btn-soft-secondary" href="{{ \App\Services\Finance\ReceiptMailer::downloadUrl($p) }}"><i class="ri-download-2-line"></i> Receipt</a>
                                    <form method="POST" action="{{ route('academy.fees.email-receipt', $p) }}">@csrf<button class="btn btn-sm btn-soft-secondary"><i class="ri-mail-send-line"></i> E-mail</button></form>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($open)
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#fee-record-{{ $inv->id }}"><i class="ri-hand-coin-line me-1"></i>Record payment</button>
                        @if($gateways)<button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#fee-online-{{ $inv->id }}"><i class="ri-secure-payment-line me-1"></i>Pay online for them</button>@endif
                        <form method="POST" action="{{ route('academy.fees.send-link', $inv) }}">@csrf<button class="btn btn-sm btn-soft-primary"><i class="ri-mail-send-line me-1"></i>E-mail pay link</button></form>
                        <form method="POST" action="{{ route('academy.fees.send-link', $inv) }}">@csrf<input type="hidden" name="email" value="0"><button class="btn btn-sm btn-soft-secondary"><i class="ri-link me-1"></i>Get link</button></form>
                    </div>

                    @if(session('pay_link.invoice') === $inv->id)
                        @php $link = session('pay_link.url'); @endphp
                        <div class="input-group input-group-sm mt-2">
                            <input class="form-control" value="{{ $link }}" readonly onclick="this.select()">
                            <button class="btn btn-soft-secondary" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Copied'">Copy</button>
                            <a class="btn btn-soft-success" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D+/', '', (string) $inv->user?->phone_number) }}?text={{ rawurlencode('Hello ' . \Illuminate\Support\Str::of((string) $inv->user?->name)->before(' ') . ', here is your secure link to pay invoice ' . $inv->number . ': ' . $link) }}"><i class="ri-whatsapp-line"></i> WhatsApp</a>
                        </div>
                        <div class="form-text">Works for 30 days, no sign-in needed.</div>
                    @endif

                    <div class="collapse mt-2" id="fee-record-{{ $inv->id }}">
                        <form method="POST" action="{{ route('academy.fees.record', $inv) }}" class="row g-2 p-2 rounded bg-light">
                            @csrf
                            <div class="col-sm-4"><label class="form-label small mb-1">Amount ({{ $inv->currency }})</label><input name="amount" type="number" step="0.01" min="0.01" max="{{ $inv->balance() }}" value="{{ $inv->balance() }}" class="form-control form-control-sm" required></div>
                            <div class="col-sm-4"><label class="form-label small mb-1">Paid by</label><select name="method" class="form-select form-select-sm" required>
                                @foreach(\App\Models\Finance\Payment::MANUAL_METHODS as $m)<option value="{{ $m }}">{{ \App\Models\Finance\Payment::METHODS[$m] }}</option>@endforeach
                            </select></div>
                            <div class="col-sm-4"><label class="form-label small mb-1">Date</label><input name="paid_at" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" class="form-control form-control-sm"></div>
                            <div class="col-sm-6"><label class="form-label small mb-1">Reference <span class="text-muted">(teller, transfer ref)</span></label><input name="reference" maxlength="100" class="form-control form-control-sm"></div>
                            <div class="col-sm-6"><label class="form-label small mb-1">Note</label><input name="notes" maxlength="500" class="form-control form-control-sm"></div>
                            <div class="col-12"><button class="btn btn-sm btn-success">Record and e-mail receipt</button> <span class="form-text">Only record money you have checked has arrived.</span></div>
                        </form>
                    </div>

                    @if($gateways)
                        <div class="collapse mt-2" id="fee-online-{{ $inv->id }}">
                            <form method="POST" action="{{ route('academy.fees.pay-online', $inv) }}" class="row g-2 p-2 rounded bg-light align-items-end">
                                @csrf
                                <div class="col-sm-5"><label class="form-label small mb-1">Amount ({{ $inv->currency }})</label><input name="amount" type="number" step="0.01" min="0.01" max="{{ $inv->balance() }}" value="{{ $inv->balance() }}" class="form-control form-control-sm" required></div>
                                <div class="col-sm-7 d-flex gap-1">
                                    @foreach($gateways as $key => $label)<button class="btn btn-sm {{ $loop->first ? 'btn-primary' : 'btn-soft-primary' }}" name="gateway" value="{{ $key }}">Pay with {{ $label }}</button>@endforeach
                                </div>
                                <div class="col-12 form-text">Opens the provider's checkout here, for the student's invoice (e.g. with the student's card at the front desk). You come back to this page after paying.</div>
                            </form>
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
        </div>
    @endif
</x-cb.card>
