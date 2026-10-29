<section class="s-section is-tight">
    <div class="s-wrap s-narrow s-reveal">
        @if($b['heading'] ?? '')<h2 class="s-h2" style="margin-bottom:24px;text-align:{{ ($b['align'] ?? 'left') === 'center' ? 'center' : 'left' }}">{{ $b['heading'] }}</h2>@endif
        <div class="s-prose {{ ($b['align'] ?? 'left') === 'center' ? 'is-center' : '' }}">{{ \App\Services\Website\Content::md($b['body'] ?? '') }}</div>
    </div>
</section>
