@extends('site.layout')
@section('title', 'Share your experience')

@section('content')
    <section class="s-section is-tight">
        <div class="s-wrap s-narrow" style="max-width:640px;text-align:left">
            @if($done ?? false)
                <div style="text-align:center">
                    <i class="ri-heart-3-fill" style="font-size:56px;color:#ff375f"></i>
                    <h1 class="s-h2">Thank you.</h1>
                    <p class="s-lead">We have your words. We will let you know when they are on our website.</p>
                    <div class="s-ctas"><a class="s-btn" href="{{ route('site.testimonials.kind', 'clients') }}">See what others say</a></div>
                </div>
            @else
                <h1 class="s-h2">Share your experience.</h1>
                <p class="s-lead" style="margin-left:0">A few sentences about working with {{ config('qudroid.company') }}. We read every one, and publish it with your name and company once we have checked it.</p>
                @if($errors->any())<div class="s-alert is-bad" style="max-width:none;margin:20px 0 0"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
                <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="s-form" style="max-width:none;margin-top:24px" data-no-loader>
                    @csrf
                    <div class="s-hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <div class="s-row">
                        <div class="s-field"><label for="t-name">Your name</label><input id="t-name" class="s-input" name="name" value="{{ old('name') }}" required maxlength="120"></div>
                        <div class="s-field"><label for="t-role">Your role</label><input id="t-role" class="s-input" name="role" value="{{ old('role') }}" maxlength="120" placeholder="e.g. Managing Director"></div>
                    </div>
                    <div class="s-row">
                        <div class="s-field"><label for="t-org">Company</label><input id="t-org" class="s-input" name="organisation" value="{{ old('organisation', $org) }}" required maxlength="150"></div>
                        <div class="s-field"><label for="t-web">Company website (optional)</label><input id="t-web" class="s-input" name="website_url" value="{{ old('website_url') }}" maxlength="500" placeholder="example.com"></div>
                    </div>
                    <div class="s-field"><label for="t-quote">What would you tell others about us?</label><textarea id="t-quote" name="quote" required minlength="20" maxlength="1500">{{ old('quote') }}</textarea></div>
                    <div class="s-row">
                        <div class="s-field"><label for="t-rating">Rating</label>
                            <select id="t-rating" name="rating"><option value="">No rating</option>@foreach([5, 4, 3, 2, 1] as $r)<option value="{{ $r }}" @selected(old('rating') == $r)>{{ str_repeat('★', $r) }} ({{ $r }})</option>@endforeach</select></div>
                        <div class="s-field"><label for="t-logo">Company logo or your photo (optional)</label><input id="t-logo" type="file" name="photo_file" accept="image/*" class="s-input"></div>
                    </div>
                    <label class="s-check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>{{ config('qudroid.company') }} may show these words, my name, role, company and picture on its website.</span></label>
                    <button class="s-btn" style="margin-top:24px">Send</button>
                </form>
            @endif
        </div>
    </section>
@endsection
