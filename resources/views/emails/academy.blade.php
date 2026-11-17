{{-- Academy e-mails: admitted, not offered, pay your fees. --}}
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subjectLine }}</title></head>
<body style="margin:0;padding:24px;background:#f3f6f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#212529">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;padding:28px">
        <div style="font-weight:600;font-size:15px;color:#405189;margin-bottom:18px">{{ config('qudroid.company', config('app.name')) }} Academy</div>
        <p style="margin:0 0 14px">{{ $greeting }}</p>
        @foreach($paragraphs as $p)
            <p style="margin:0 0 14px;line-height:1.5">{{ $p }}</p>
        @endforeach
        @if($rows)
            <table role="presentation" style="width:100%;border-collapse:collapse;margin:6px 0 18px;font-size:14px">
                @foreach($rows as [$label, $value])
                    <tr>
                        <td style="padding:8px 10px;border-bottom:1px solid #eef0f3;color:#6c757d;vertical-align:top">{{ $label }}</td>
                        <td style="padding:8px 10px;border-bottom:1px solid #eef0f3;text-align:right;font-weight:600">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
        @if($buttonText && $buttonUrl)
            <p style="margin:18px 0;text-align:center"><a href="{{ $buttonUrl }}" style="display:inline-block;background:#405189;color:#fff;text-decoration:none;padding:11px 22px;border-radius:6px;font-weight:600">{{ $buttonText }}</a></p>
        @endif
        @foreach($after as $p)
            <p style="margin:0 0 12px;line-height:1.5;font-size:14px;color:#555">{{ $p }}</p>
        @endforeach
    </div>
    <p style="text-align:center;font-size:12px;color:#878a99;margin-top:14px">Sent by {{ config('qudroid.company', config('app.name')) }}.</p>
</body>
</html>
