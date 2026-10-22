@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Record an expense" icon="ri-shopping-bag-3-line" subtitle="It goes to an approver before it can be paid." :back="route('finance.expenses.index')" back-label="Expenses" />

    @include('partials.flash')

    <div class="row"><div class="col-xl-8">
        <x-cb.card>
            <form method="POST" action="{{ route('finance.expenses.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
                    <div class="col-md-8"><label class="form-label">Paid to</label><input name="payee" class="form-control" value="{{ old('payee') }}" maxlength="150" required placeholder="Supplier or person"></div>
                    <div class="col-12"><label class="form-label">What it was for</label><textarea name="description" class="form-control" rows="2" maxlength="500" required>{{ old('description') }}</textarea></div>
                    <div class="col-md-8"><label class="form-label">Category</label>
                        <select name="account_id" class="form-select" required>
                            <option value="">Choose…</option>
                            @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((int) old('account_id') === $a->id)>{{ $a->label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Amount ({{ config('qudroid.academy.currency', 'NGN') }})</label><input type="number" name="amount" step="0.01" min="0.01" class="form-control" value="{{ old('amount') }}" required></div>
                    <div class="col-12"><label class="form-label">Receipt or bill <span class="text-muted">(optional)</span></label><input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.heic,.webp"><div class="form-text">PDF or photo, up to 10 MB.</div></div>
                </div>
                <div class="mt-4"><button class="btn btn-primary">Send for approval</button></div>
            </form>
        </x-cb.card>
    </div></div>

</div></div></div>
@endsection
