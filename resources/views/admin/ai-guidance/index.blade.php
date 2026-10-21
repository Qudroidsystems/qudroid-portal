{{-- resources/views/admin/ai-guidance/index.blade.php --}}
@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <x-cb.hero title="AI Learning Guidance" icon="ri-sparkling-2-line" subtitle="Personal reading plans, learning style, interests and university course suggestions for students and parents in the mobile app.">
        <x-slot:pills>
            @if(!$s->enabled)
                <span class="cb-meta-pill"><span class="status-pill st-danger">Switched off</span></span>
            @elseif($s->usesAi())
                <span class="cb-meta-pill"><span class="status-pill st-paid">Using {{ $providers[$s->provider][0] }}</span></span>
                <span class="cb-meta-pill"><i class="ri-cpu-line"></i>{{ $s->modelName() }}</span>
            @else
                <span class="cb-meta-pill"><span class="status-pill st-warning">Built-in rules</span></span>
            @endif
            <span class="cb-meta-pill"><i class="ri-file-list-3-line"></i>{{ number_format($stats['reports']) }} reports · {{ number_format($stats['students']) }} students</span>
        </x-slot:pills>
    </x-cb.hero>

    @if(session('success'))<div class="cb-banner info"><i class="ri-checkbox-circle-line"></i><div>{{ session('success') }}</div></div>@endif
    @if(session('error'))<div class="cb-banner warning"><i class="ri-error-warning-line"></i><div>{{ session('error') }}</div></div>@endif
    @if($errors->any())<div class="cb-banner warning"><i class="ri-error-warning-line"></i><div>{{ $errors->first() }}</div></div>@endif

    <form method="POST" action="{{ route('ai-guidance.save') }}" autocomplete="off">@csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Which AI writes the guidance" icon="ri-robot-2-line">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="enabled" @checked(old('enabled', $s->enabled))>
                        <label class="form-check-label fw-semibold" for="enabled">Show learning guidance in the mobile app</label>
                    </div>

                    <div class="row g-2">
                        @foreach($providers as $key => [$label, $defaultModel])
                            <div class="col-md-6">
                                <label class="d-flex gap-2 align-items-start border rounded-3 p-3 h-100" style="cursor:pointer">
                                    <input class="form-check-input mt-1" type="radio" name="provider" value="{{ $key }}" @checked(old('provider', $s->provider) === $key)>
                                    <span>
                                        <span class="fw-semibold d-block">{{ $label }}</span>
                                        <span class="small text-muted">
                                            @if($key === 'rules') Free. Suggestions are worked out by the portal from scores and teacher ratings. No data leaves the school.
                                            @else Richer, more personal writing. Needs your own API key; usage is billed by the provider. Default model: {{ $defaultModel }}.
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Model (optional)</label>
                            <input type="text" name="model" class="form-control" maxlength="100" value="{{ old('model', $s->model) }}" placeholder="Leave empty for the default">
                            <div class="form-text">Type a newer model name here when your provider releases one.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API key</label>
                            <input type="password" name="api_key" class="form-control" maxlength="500" placeholder="{{ $hasKey ? '•••••••• saved — type to replace' : 'Paste the key from your provider' }}">
                            @if($hasKey)
                                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_key" value="1" id="rk"><label class="form-check-label small" for="rk">Remove the saved key</label></div>
                            @endif
                            <div class="form-text">Stored encrypted. It is only used by the portal's server — never sent to phones.</div>
                        </div>
                    </div>
                </x-cb.card>

                <x-cb.card title="About your school" icon="ri-school-line">
                    <label class="form-label">Context the AI should keep in mind</label>
                    <textarea name="school_context" class="form-control" rows="3" maxlength="1000">{{ old('school_context', $s->school_context) }}</textarea>
                    <div class="form-text">e.g. curriculum, exams students sit (WAEC, NECO, JAMB), local universities, the school's values.</div>
                </x-cb.card>
            </div>

            <div class="col-xl-4">
                <x-cb.card title="Who sees it" icon="ri-team-line">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="for_parents" value="1" id="fp" @checked(old('for_parents', $s->for_parents))><label class="form-check-label" for="fp">Parents (for their children)</label></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="for_students" value="1" id="fs" @checked(old('for_students', $s->for_students))><label class="form-check-label" for="fs">Students (their own)</label></div>
                    <label class="form-label">Refreshes allowed per student per term</label>
                    <input type="number" name="refreshes_per_term" class="form-control" min="1" max="20" value="{{ old('refreshes_per_term', $s->refreshes_per_term) }}">
                    <div class="form-text">Guidance is rebuilt automatically when new results are published; this limits manual refreshes (and AI cost).</div>
                </x-cb.card>

                <x-cb.card title="Privacy" icon="ri-shield-user-line">
                    <p class="small mb-0">Only anonymous school records are used: class level, subject scores per term, attendance rate, teacher ratings (e.g. punctuality, reading skill) and e-learning marks. <strong>Names, photos, contacts and addresses are never sent</strong> to an AI provider. Results on hold for fees are left out.</p>
                </x-cb.card>

                <x-cb.card title="Usage" icon="ri-bar-chart-2-line">
                    <div class="d-flex justify-content-between small"><span>Reports this month</span><strong>{{ $stats['month'] }}</strong></div>
                    <div class="d-flex justify-content-between small"><span>Written by AI (all time)</span><strong>{{ $stats['ai'] }}</strong></div>
                </x-cb.card>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary" name="action" value="save"><i class="ri-save-3-line me-1"></i>Save settings</button>
                    <button class="btn btn-outline-primary" name="action" value="test"><i class="ri-plug-line me-1"></i>Save &amp; test connection</button>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('ai-guidance.reset') }}" class="mt-3" onsubmit="return confirm('Clear all stored guidance? It is rebuilt when students open it again.');">@csrf
        <button class="btn btn-sm btn-outline-danger"><i class="ri-refresh-line me-1"></i>Clear stored guidance</button>
        <span class="small text-muted ms-2">Use after changing the AI so everyone gets a fresh report.</span>
    </form>
</div>
</div>
</div>
@endsection
