@extends('site.layout')
@section('title', 'Register: ' . $c->title)

@section('content')
    <section class="s-section is-tight">
        <div class="s-wrap s-narrow">
            <a class="s-more is-back" href="{{ route('site.academy.course', $c->slug) }}">{{ $c->title }}</a>
            <h1 class="s-h2" style="margin-top:12px">Register.</h1>
            <p class="s-lead">{{ $cohort->name }}@if($cohort->starts_on) · starts {{ $cohort->starts_on->format('j M Y') }}@endif · {{ \App\Services\Website\Content::money($cohort->effectiveFee(), $c->currency) }}</p>

            @if($errors->any())
                <div class="s-alert is-bad"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('site.academy.store', [$c->slug, $cohort]) }}" class="s-form" data-no-loader>
                @csrf
                <div class="s-hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

                @if($student)
                    <div class="s-alert is-info" style="margin:0 0 24px">Registering as <b>{{ $student->user->name }}</b> ({{ $student->student_no }}).</div>
                @else
                    <h3 class="s-h3" style="margin-bottom:16px">About you</h3>
                    <div class="s-field"><label for="r-name">Full name</label><input id="r-name" class="s-input" name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name"></div>
                    <div class="s-row">
                        <div class="s-field"><label for="r-email">E-mail</label><input id="r-email" class="s-input" type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email"></div>
                        <div class="s-field"><label for="r-phone">Phone</label><input id="r-phone" class="s-input" name="phone_number" value="{{ old('phone_number') }}" required maxlength="30" autocomplete="tel"></div>
                    </div>
                    <div class="s-row">
                        <div class="s-field"><label for="r-gender">Gender (optional)</label>
                            <select id="r-gender" name="gender"><option value="">Prefer not to say</option><option value="male" @selected(old('gender') === 'male')>Male</option><option value="female" @selected(old('gender') === 'female')>Female</option></select></div>
                        <div class="s-field"><label for="r-edu">Highest education (optional)</label><input id="r-edu" class="s-input" name="education_level" value="{{ old('education_level') }}" maxlength="80" placeholder="e.g. SSCE, OND, BSc"></div>
                    </div>
                    <div class="s-row">
                        <div class="s-field"><label for="r-occ">Occupation (optional)</label><input id="r-occ" class="s-input" name="occupation" value="{{ old('occupation') }}" maxlength="120"></div>
                        <div class="s-field"><label for="r-ref">How did you hear about us? (optional)</label><input id="r-ref" class="s-input" name="referral_source" value="{{ old('referral_source') }}" maxlength="120"></div>
                    </div>
                    <p class="s-muted" style="font-size:14px;margin:0 0 24px">Already have an account? <a href="{{ route('login') }}">Sign in</a> first.</p>
                @endif

                <h3 class="s-h3" style="margin-bottom:16px">A little about your goals</h3>
                <div class="s-row">
                    <div class="s-field"><label for="r-exp">Coding experience</label>
                        <select id="r-exp" name="experience_level" required><option value="">Choose…</option>
                            @foreach(\App\Models\Academy\Application::EXPERIENCE as $k => $v)<option value="{{ $k }}" @selected(old('experience_level') === $k)>{{ $v }}</option>@endforeach
                        </select></div>
                    <div class="s-field"><label for="r-laptop">Do you have a laptop you can use?</label>
                        <select id="r-laptop" name="has_laptop" required><option value="">Choose…</option><option value="1" @selected(old('has_laptop') === '1')>Yes</option><option value="0" @selected(old('has_laptop') === '0')>No</option></select></div>
                </div>
                <div class="s-field"><label for="r-why">Why do you want to join this course?</label>
                    <textarea id="r-why" name="motivation" required minlength="20" maxlength="2000">{{ old('motivation') }}</textarea></div>
                @unless($student)
                    <label class="s-check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                        <span>I agree that {{ $cfg['name'] }} may store these details to process my registration and contact me about it.</span></label>
                @endunless
                <button class="s-btn" style="margin-top:24px">{{ $c->instant_enrollment ? 'Register and continue to payment' : 'Submit registration' }}</button>
            </form>
        </div>
    </section>
@endsection
