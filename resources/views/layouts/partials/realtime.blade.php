{{--
    Instant updates over Laravel Reverb. When it is on, window.QRealtime.on(channel, event, fn)
    listens on a private channel ("user.ID", "connect.c.ID", "call.ID"); pages still poll as
    before, just less often (QRealtime.connected tells them). When it is off, nothing loads.
--}}
@php $rt = auth()->check() ? \App\Services\Realtime\Realtime::clientConfig() : null; @endphp
@if($rt)
<script src="{{ asset('vendor/pusher-js/pusher.min.js') }}"></script>
<script src="{{ asset('vendor/laravel-echo/echo.iife.js') }}"></script>
<script>
(function () {
    const cfg = @json($rt);
    const me = @json(auth()->id());
    const waiting = [];
    let echo = null;
    const api = {
        connected: false,
        me,
        on(channel, event, fn) {
            if (!echo) { waiting.push([channel, event, fn]); return; }
            echo.private(channel).listen('.' + event, fn);
        },
        off(channel) { echo?.leave(channel); },
    };
    window.QRealtime = api;
    try {
        echo = new Echo.default({
            broadcaster: 'reverb', key: cfg.key, wsHost: cfg.host, wsPort: cfg.port, wssPort: cfg.port, forceTLS: cfg.tls,
            enabledTransports: ['ws', 'wss'], authEndpoint: @json(url('/broadcasting/auth')),
            auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' } },
        });
        const conn = echo.connector.pusher.connection;
        conn.bind('state_change', s => {
            api.connected = s.current === 'connected';
            document.dispatchEvent(new CustomEvent('realtime:state', { detail: { connected: api.connected } }));
        });
        waiting.splice(0).forEach(([c, e, f]) => api.on(c, e, f));
        // The bell and incoming calls, on every page.
        api.on('user.' + me, 'notification', () => document.dispatchEvent(new CustomEvent('realtime:notification')));
        api.on('user.' + me, 'call', () => document.dispatchEvent(new CustomEvent('realtime:call')));
    } catch (e) { console.warn('Realtime off:', e); }
})();
</script>
@endif
