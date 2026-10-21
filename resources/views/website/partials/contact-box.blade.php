<div class="box">
    <h4>Talk to us</h4>
    <div class="info-list">
        @foreach(array_slice($school->phones, 0, 2) as $ph)
            <div class="it"><i class="ri-phone-line"></i><div><b>Call</b><a href="tel:{{ preg_replace('/\s+/', '', $ph) }}">{{ $ph }}</a></div></div>
        @endforeach
        @if($school->email)<div class="it"><i class="ri-mail-line"></i><div><b>E-mail</b><a href="mailto:{{ $school->email }}">{{ $school->email }}</a></div></div>@endif
        @if(!$school->phones && !$school->email)<a class="link-arrow" href="{{ route('site.contact') }}">Contact the school <i class="ri-arrow-right-line"></i></a>@endif
    </div>
</div>
