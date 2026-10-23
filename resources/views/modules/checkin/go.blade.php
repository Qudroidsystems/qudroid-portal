@extends('layouts.guest')
@section('title', 'Check-in')

@section('content')
<div class="apply-course p-4 p-md-5 text-center mx-auto" style="max-width:520px">
    @if($result)
        @php $late = $result['record']->status === 'late'; $out = $result['state'] === 'out'; @endphp
        <i class="{{ $out ? 'ri-logout-box-r-line text-info' : ($late ? 'ri-time-line text-warning' : 'ri-checkbox-circle-fill text-success') }}" style="font-size:80px"></i>
        <h3 class="fw-semibold mt-2">{{ $result['message'] }}</h3>
        <p class="text-muted mb-1">{{ $event->title }}</p>
        <p class="text-muted small">{{ now()->format('l j F, g:i A') }}</p>
    @elseif(!empty($askLocation))
        <i class="ri-map-pin-line text-primary" style="font-size:64px"></i>
        <h4 class="fw-semibold mt-2">One more step</h4>
        <p class="text-muted">{{ $error ?: 'We check that you are at ' . ($event->location ?: 'the venue') . '. Allow location when your phone asks.' }}</p>
        <form method="POST" action="{{ route('checkin.go', $event) }}" id="locForm">@csrf
            <input type="hidden" name="lat"><input type="hidden" name="lng">
            <button type="button" class="btn btn-primary" id="locBtn">Share my location and check in</button>
        </form>
        <p class="small text-danger mt-2" id="locErr"></p>
        <script>
            document.getElementById('locBtn').addEventListener('click', function () {
                var f = document.getElementById('locForm'), err = document.getElementById('locErr');
                if (!navigator.geolocation) { err.textContent = 'This browser cannot share location.'; return; }
                this.disabled = true; this.textContent = 'Finding you…';
                var btn = this;
                navigator.geolocation.getCurrentPosition(function (p) { f.lat.value = p.coords.latitude; f.lng.value = p.coords.longitude; f.submit(); },
                    function () { err.textContent = 'Location was not shared. Turn it on and try again.'; btn.disabled = false; btn.textContent = 'Share my location and check in'; },
                    { enableHighAccuracy: true, timeout: 15000 });
            });
        </script>
    @else
        <i class="ri-error-warning-line text-danger" style="font-size:64px"></i>
        <h4 class="fw-semibold mt-2">Not checked in</h4>
        <p class="text-muted">{{ $error }}</p>
    @endif
    <a href="{{ route('checkin.index') }}" class="btn btn-light btn-sm mt-2">My check-ins</a>
</div>
@endsection
