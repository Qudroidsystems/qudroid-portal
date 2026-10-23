{{-- A guest's ticket. The QR is embedded as a picture so it shows without loading anything. --}}
@php
    $png = \App\Services\Checkin\Qr::png(\App\Services\Checkin\CheckinService::guestPayload($guest), 8);
    $ticketUrl = route('checkin.public.ticket', [$event->slug ?: $event->id, $guest->code]);
@endphp
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Your ticket</title></head>
<body style="margin:0;padding:24px;background:#f3f6f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#212529">
    <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:8px;padding:28px;text-align:center">
        <div style="font-weight:600;font-size:15px;color:#405189;margin-bottom:18px">{{ config('app.name') }}</div>
        <p style="margin:0 0 6px">Hello {{ \Illuminate\Support\Str::of($guest->name)->before(' ') }}, you're registered for</p>
        <h2 style="margin:0 0 6px;font-size:20px">{{ $event->title }}</h2>
        <p style="margin:0 0 4px;color:#555">{{ $event->whenLabel() }}</p>
        @if($event->location)<p style="margin:0 0 16px;color:#555">{{ $event->location }}</p>@endif
        <img src="{{ $message->embedData($png, 'ticket.png', 'image/png') }}" alt="Ticket QR code" width="220" height="220" style="display:block;margin:12px auto">
        <p style="margin:6px 0 0;font-size:13px;color:#555">Show this QR at the door. Ticket code:</p>
        <p style="margin:2px 0 18px;font-size:22px;letter-spacing:3px;font-weight:700">{{ $guest->code }}</p>
        <a href="{{ $ticketUrl }}" style="display:inline-block;background:#405189;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px">Open my ticket</a>
    </div>
    <p style="text-align:center;font-size:12px;color:#878a99;margin-top:14px">Sent by {{ config('app.name') }}.</p>
</body>
</html>
