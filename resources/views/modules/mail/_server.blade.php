{{-- IMAP/SMTP fields with presets. $v: current values (array or model). --}}
@php
    $v = $v ?? [];
    $val = fn ($k, $d = null) => old($k, data_get($v, $k) ?? $d);
    $presets = [
        'company' => ['Our mail server', $server['imap_host'] ?? '', $server['imap_port'] ?? 993, $server['imap_encryption'] ?? 'ssl', $server['smtp_host'] ?? '', $server['smtp_port'] ?? 465, $server['smtp_encryption'] ?? 'ssl'],
        'gmail' => ['Gmail / Google Workspace', 'imap.gmail.com', 993, 'ssl', 'smtp.gmail.com', 465, 'ssl'],
        'outlook' => ['Outlook / Microsoft 365', 'outlook.office365.com', 993, 'ssl', 'smtp.office365.com', 587, 'tls'],
        'zoho' => ['Zoho Mail', 'imap.zoho.com', 993, 'ssl', 'smtp.zoho.com', 465, 'ssl'],
        'yahoo' => ['Yahoo Mail', 'imap.mail.yahoo.com', 993, 'ssl', 'smtp.mail.yahoo.com', 465, 'ssl'],
    ];
    if (empty($server['imap_host'])) unset($presets['company']);
    $enc = ['ssl' => 'SSL/TLS', 'tls' => 'STARTTLS', 'none' => 'None'];
@endphp
<div class="qm-server">
    <div class="mb-2">
        <label class="form-label small mb-1">Provider</label>
        <select class="form-select form-select-sm" data-preset>
            <option value="">Other (enter the details)</option>
            @foreach($presets as $k => $p)<option value="{{ $k }}" data-p='@json(array_slice($p, 1))' @selected($val('imap_host') === $p[1])>{{ $p[0] }}</option>@endforeach
        </select>
        <div class="form-text">Gmail, Outlook, Yahoo and Zoho need an <strong>app password</strong> (made in that account's security settings), not your normal password.</div>
    </div>
    <div class="row g-2">
        <div class="col-md-6"><label class="form-label small mb-1">Incoming server (IMAP)</label><input name="imap_host" class="form-control form-control-sm" value="{{ $val('imap_host') }}" required></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Port</label><input name="imap_port" type="number" class="form-control form-control-sm" value="{{ $val('imap_port', 993) }}" required></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Security</label><select name="imap_encryption" class="form-select form-select-sm">@foreach($enc as $k => $l)<option value="{{ $k }}" @selected($val('imap_encryption', 'ssl') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label small mb-1">Outgoing server (SMTP)</label><input name="smtp_host" class="form-control form-control-sm" value="{{ $val('smtp_host') }}" required></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Port</label><input name="smtp_port" type="number" class="form-control form-control-sm" value="{{ $val('smtp_port', 465) }}" required></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Security</label><select name="smtp_encryption" class="form-select form-select-sm">@foreach($enc as $k => $l)<option value="{{ $k }}" @selected($val('smtp_encryption', 'ssl') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label small mb-1">Username <span class="text-muted">(if not the e-mail address)</span></label><input name="username" class="form-control form-control-sm" value="{{ $val('username') }}" autocomplete="off"></div>
    </div>
</div>
@once
@push('scripts')
<script>
document.addEventListener('change', function (e) {
    if (!e.target.matches('[data-preset]') || !e.target.value) return;
    var p = JSON.parse(e.target.selectedOptions[0].dataset.p), f = e.target.closest('.qm-server');
    ['imap_host', 'imap_port', 'imap_encryption', 'smtp_host', 'smtp_port', 'smtp_encryption'].forEach(function (k, i) { f.querySelector('[name="' + k + '"]').value = p[i]; });
});
</script>
@endpush
@endonce
