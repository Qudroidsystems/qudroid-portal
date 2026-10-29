@php($form = \App\Services\Website\Content::form($b['form'] ?? null))
@if($form)
    <section class="s-section" id="form-{{ $form->slug }}">
        <div class="s-wrap s-reveal">
            <h2 class="s-h2">{{ ($b['heading'] ?? '') ?: $form->name }}</h2>
            @if(($b['intro'] ?? '') ?: $form->intro)<p class="s-lead">{{ ($b['intro'] ?? '') ?: $form->intro }}</p>@endif
            @include('site.partials.form', ['form' => $form])
        </div>
    </section>
@endif
