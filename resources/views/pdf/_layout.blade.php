{{-- Shared frame for PDF documents (dompdf: tables and plain CSS only). --}}
@php
    $logoFile = public_path($brand['logo'] ?? '');
    $logo = is_file($logoFile) ? 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logoFile)) : null;
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>@yield('title')</title>
<style>
    @page { margin: 28px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: top; }
    .brand { font-size: 16px; font-weight: bold; }
    .muted { color: #64748b; }
    .doc-title { font-size: 22px; font-weight: bold; letter-spacing: 2px; color: #4f46e5; text-align: right; }
    .meta td { padding: 1px 0; }
    .lines { margin-top: 18px; }
    .lines th { background: #eef2ff; color: #3730a3; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; }
    .lines td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
    .num { text-align: right; white-space: nowrap; }
    .totals { width: 46%; margin-left: 54%; margin-top: 10px; }
    .totals td { padding: 4px 8px; }
    .totals .grand td { border-top: 2px solid #1e293b; font-weight: bold; font-size: 13px; }
    .box { margin-top: 16px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .stamp { position: fixed; top: 300px; left: 120px; font-size: 90px; font-weight: bold; color: #dc2626; opacity: .18; transform: rotate(-24deg); }
    .foot { position: fixed; bottom: -8px; left: 0; right: 0; text-align: center; font-size: 9px; color: #94a3b8; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: bold; }
</style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 55%">
                @if($logo)<img src="{{ $logo }}" style="height: 38px"><br>@else<div class="brand">{{ $brand['company'] ?? 'Qudroid Systems' }}</div>@endif
                <div class="muted" style="margin-top: 6px">
                    {{ $brand['company'] ?? 'Qudroid Systems' }}<br>
                    @if(!empty($brand['address'])){{ $brand['address'] }}<br>@endif
                    {{ $brand['support_email'] ?? '' }}@if(!empty($brand['support_phone'])) · {{ $brand['support_phone'] }}@endif<br>
                    {{ $brand['website'] ?? '' }}
                </div>
            </td>
            <td style="width: 45%">
                <div class="doc-title">@yield('doc')</div>
                @yield('meta')
            </td>
        </tr>
    </table>

    @yield('body')

    @hasSection('stamp')<div class="stamp">@yield('stamp')</div>@endif
    <div class="foot">{{ $brand['company'] ?? 'Qudroid Systems' }} · generated {{ now()->format('j M Y, g:ia') }}</div>
</body>
</html>
