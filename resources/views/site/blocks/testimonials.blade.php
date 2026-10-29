@if(!empty($b['items']))
    <section class="s-section">
        <div class="s-wrap s-reveal">
            @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
            <div class="s-quotes">
                @foreach($b['items'] as $q)
                    <blockquote class="s-quote"><p>“{{ $q['quote'] ?? '' }}”</p><footer><b>{{ $q['name'] ?? '' }}</b>{{ $q['role'] ?? '' }}</footer></blockquote>
                @endforeach
            </div>
        </div>
    </section>
@endif
