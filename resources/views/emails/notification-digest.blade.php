{{-- The morning summary of unread notifications. --}}
@php $tint = config('qudroid.primary_color', '#405189'); @endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>What you missed</title></head>
<body style="margin:0;padding:24px 12px;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1d1d1f">
    <div style="max-width:560px;margin:0 auto">
        <div style="font-weight:600;font-size:14px;color:{{ $tint }};margin:0 4px 14px">{{ config('app.name') }}</div>
        <h1 style="font-size:26px;letter-spacing:-.02em;margin:0 4px 4px">Good morning, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h1>
        <p style="margin:0 4px 18px;color:#6e6e73;font-size:15px">{{ $total === 1 ? 'One thing is' : $total . ' things are' }} waiting for you.</p>
        @foreach($items as $n)
            <a href="{{ $n['open_url'] }}" style="display:block;background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:8px;text-decoration:none;color:#1d1d1f">
                <div style="font-size:11px;color:#6e6e73;text-transform:uppercase;letter-spacing:.02em;margin-bottom:3px">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:{{ $n['color'] }};margin-right:6px"></span>{{ $n['group_label'] }} · {{ $n['time'] }}
                </div>
                <div style="font-weight:600;font-size:15px">{{ $n['title'] }}</div>
                @if($n['body'])<div style="font-size:14px;color:#3a3a3c;margin-top:2px">{{ \Illuminate\Support\Str::limit($n['body'], 160) }}</div>@endif
                @if($n['actions'])<div style="font-size:13px;color:{{ $tint }};margin-top:6px">Needs your answer</div>@endif
            </a>
        @endforeach
        @if($total > count($items))
            <p style="margin:6px 4px;color:#6e6e73;font-size:14px">And {{ $total - count($items) }} more.</p>
        @endif
        <p style="text-align:center;margin:20px 0 0"><a href="{{ route('notifications.index') }}" style="display:inline-block;background:{{ $tint }};color:#fff;text-decoration:none;padding:10px 22px;border-radius:980px;font-weight:500">Open notifications</a></p>
        <p style="text-align:center;font-size:12px;color:#86868b;margin-top:18px">You get this because the daily summary is on. <a href="{{ route('notifications.index') }}#settings" style="color:#86868b">Turn it off</a>.</p>
    </div>
</body>
</html>
