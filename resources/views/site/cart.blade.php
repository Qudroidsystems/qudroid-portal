@extends('site.layout')
@section('title', 'Your courses')
@php($money = fn ($v, $c) => \App\Services\Website\Content::money($v, $c))

@section('content')
    <section class="s-section is-tight">
        <div class="s-wrap">
            <div class="s-cart">
                <a class="s-more is-back" href="{{ route('site.academy') }}">All courses</a>
                <h1 class="s-h2" style="margin-top:12px">Your courses.</h1>

                @if($errors->any())
                    <div class="s-alert is-bad" style="margin:16px 0 0;max-width:none"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                @if($items->isEmpty())
                    <p class="s-lead">Your cart is empty. Choose a course and a class to get started.</p>
                    <div class="s-ctas"><a class="s-btn" href="{{ route('site.academy') }}">Browse courses</a></div>
                @else
                    <p class="s-lead">Register for all of them in one go. {{ $instant ? 'Your places are confirmed straight away and you can pay online.' : 'We review your registration, then e-mail your admission number and a link to pay.' }}</p>

                    @foreach($items as $cohort)
                        <div class="s-cart-line">
                            <div>
                                <b><a href="{{ route('site.academy.course', $cohort->course->slug) }}" style="color:inherit">{{ $cohort->course->title }}</a></b>
                                <small>{{ $cohort->name }} · {{ $cohort->starts_on ? 'starts ' . $cohort->starts_on->format('j M Y') : 'start date to be announced' }} · {{ \App\Models\Academy\Course::MODES[$cohort->effectiveMode()] ?? ucfirst($cohort->effectiveMode()) }}</small>
                                @if($cohort->course->installments_allowed)<small>Pay in instalments, from {{ $money($cohort->course->min_deposit ?: 0, $cohort->course->currency) }} to start</small>@endif
                            </div>
                            <div class="s-cart-end">
                                <span class="s-price" style="font-size:19px">{{ $money($cohort->effectiveFee(), $cohort->course->currency) }}</span>
                                <form method="POST" action="{{ route('site.academy.cart.remove', $cohort) }}" data-no-loader>@csrf
                                    <button class="s-cart-x" title="Remove {{ $cohort->course->title }}" aria-label="Remove {{ $cohort->course->title }}"><i class="ri-close-line"></i></button>
                                </form>
                            </div>
                        </div>
                    @endforeach

                    <div class="s-pay-box" style="margin-top:20px">
                        @foreach($totals as $currency => $total)
                            <div class="s-pay-total" style="border:0;margin:0;padding:4px 0"><span>{{ $loop->first ? 'Total' : '' }} ({{ $items->count() }} {{ \Illuminate\Support\Str::plural('course', $items->count()) }})</span><b>{{ $money($total, $currency) }}</b></div>
                        @endforeach
                        <p class="s-muted" style="font-size:13px;margin:6px 0 0">{{ $instant ? 'You pay after registering.' : 'Nothing to pay now. You pay once you are admitted.' }}</p>
                    </div>
                    <div class="s-ctas" style="justify-content:flex-start;margin-top:16px"><a class="s-more" href="{{ route('site.academy') }}">Add another course</a></div>

                    <form method="POST" action="{{ route('site.academy.checkout') }}" class="s-form" style="max-width:none" data-no-loader>
                        @csrf
                        <div class="s-hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

                        @if($student)
                            <div class="s-alert is-info" style="margin:0 0 24px;max-width:none">Registering as <b>{{ $student->user->name }}</b>@if($student->student_no) ({{ $student->student_no }})@endif.</div>
                        @elseif(auth()->check())
                            <div class="s-alert is-bad" style="margin:0 0 24px;max-width:none">You are signed in with a staff account. Sign out to register as a student.</div>
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
                        <div class="s-field"><label for="r-why">Why do you want to join {{ $items->count() > 1 ? 'these courses' : 'this course' }}?</label>
                            <textarea id="r-why" name="motivation" required minlength="20" maxlength="2000">{{ old('motivation') }}</textarea></div>
                        @unless($student)
                            <label class="s-check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                                <span>I agree that {{ $cfg['name'] }} may store these details to process my registration and contact me about it.</span></label>
                        @endunless
                        <button class="s-btn" style="margin-top:24px" @disabled(!$student && auth()->check())>{{ $instant ? 'Register and continue to payment' : 'Submit registration' }}</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
