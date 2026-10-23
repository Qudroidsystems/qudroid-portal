{{-- A workflow's e-mail: plain text from the builder, kept simple so it reads well in any mail app. --}}
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subject }}</title></head>
<body style="margin:0;padding:24px;background:#f3f6f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#212529">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;padding:28px">
        <div style="font-weight:600;font-size:15px;color:#405189;margin-bottom:18px">{{ $brand['name'] ?? config('app.name') }}</div>
        @if($name)<p style="margin:0 0 14px">Hello {{ \Illuminate\Support\Str::of($name)->before(' ') }},</p>@endif
        <div style="font-size:15px;line-height:1.6">{!! nl2br(e($body)) !!}</div>
        @if($link)
            <p style="margin:22px 0 0"><a href="{{ $link }}" style="display:inline-block;background:#405189;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px">Open in the portal</a></p>
        @endif
    </div>
    <p style="text-align:center;font-size:12px;color:#878a99;margin-top:14px">Sent automatically by {{ $brand['name'] ?? config('app.name') }}.</p>
</body>
</html>
