@php
    if (in_array($b['source'] ?? 'manual', ['all', 'students', 'clients'], true)) $b['items'] = \App\Services\Website\Content::quotes($b['source']);
    $more = ($b['source'] ?? 'manual') !== 'manual';
@endphp
@if(!empty($b['items']))
    <section class="s-section">
        <div class="s-wrap s-reveal">
            @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
            <div class="s-slider" data-slider>
                <div class="s-slides s-stagger">
                    @foreach($b['items'] as $q)
                        <blockquote class="s-quote"><i class="ri-double-quotes-l" aria-hidden="true"></i><p>{{ $q['quote'] ?? '' }}</p><footer>@if(!empty($q['photo']))<img class="s-quote-photo" src="{{ $q['photo'] }}" alt="" loading="lazy">@endif<b>{{ $q['name'] ?? '' }}</b>{{ $q['role'] ?? '' }}</footer></blockquote>
                    @endforeach
                </div>
                @if(count($b['items']) > 1)
                    <div class="s-slider-nav">
                        <button type="button" data-prev aria-label="Previous"><i class="ri-arrow-left-s-line"></i></button>
                        <span class="s-dots">@foreach($b['items'] as $q)<button type="button" data-dot="{{ $loop->index }}" aria-label="Quote {{ $loop->iteration }}"></button>@endforeach</span>
                        <button type="button" data-next aria-label="Next"><i class="ri-arrow-right-s-line"></i></button>
                    </div>
                @endif
            </div>
            @if($more)<div class="s-ctas"><a class="s-more" href="{{ ($b['source'] ?? '') === 'all' ? route('site.testimonials') : route('site.testimonials.kind', $b['source']) }}">Read more stories</a></div>@endif
        </div>
    </section>
@endif
