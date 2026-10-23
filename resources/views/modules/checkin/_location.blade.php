{{-- Optional "must be near here" check. $m has lat, lng, radius_m, require_location. --}}
<div class="border rounded p-2 small">
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="require_location" value="1" id="reqloc{{ $m->id ?? 'n' }}" @checked(old('require_location', $m->require_location))>
        <label class="form-check-label" for="reqloc{{ $m->id ?? 'n' }}">Only accept phones near the venue (for the door QR)</label></div>
    <div class="row g-2" data-loc>
        <div class="col-4"><input name="lat" class="form-control form-control-sm" placeholder="Latitude" value="{{ old('lat', $m->lat) }}" aria-label="Latitude"></div>
        <div class="col-4"><input name="lng" class="form-control form-control-sm" placeholder="Longitude" value="{{ old('lng', $m->lng) }}" aria-label="Longitude"></div>
        <div class="col-4"><div class="input-group input-group-sm"><input name="radius_m" type="number" min="20" class="form-control" value="{{ old('radius_m', $m->radius_m ?? 200) }}" aria-label="Radius in metres"><span class="input-group-text">m</span></div></div>
    </div>
    <button type="button" class="btn btn-link btn-sm px-0" onclick="var b=this,f=b.previousElementSibling;navigator.geolocation&&navigator.geolocation.getCurrentPosition(function(p){f.querySelector('[name=lat]').value=p.coords.latitude.toFixed(6);f.querySelector('[name=lng]').value=p.coords.longitude.toFixed(6);},function(){alert('Could not get your location.');})"><i class="ri-map-pin-line"></i> Use where I am now</button>
</div>
