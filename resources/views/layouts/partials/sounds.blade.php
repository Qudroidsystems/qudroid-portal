{{-- Portal sounds, made in the browser (no audio files): call ringtone and ringback, notification chime, message tone.
     Browsers only allow sound after the person has clicked or typed on the page once; until then sounds are skipped.
     window.QSound.setOn(false) mutes them on this device. --}}
<script>
window.QSound = window.QSound || (function () {
    const KEY = 'qsound:on';
    let ctx = null, last = {};
    function on() { if (window.QPresence && window.QPresence.dnd) return false; try { return localStorage.getItem(KEY) !== '0'; } catch (e) { return true; } }
    function setOn(v) { try { localStorage.setItem(KEY, v ? '1' : '0'); } catch (e) {} document.dispatchEvent(new CustomEvent('qsound:change', { detail: v })); }
    function audio() {
        if (!ctx) { const A = window.AudioContext || window.webkitAudioContext; if (!A) return null; ctx = new A(); }
        if (ctx.state === 'suspended') ctx.resume().catch(() => {});
        return ctx;
    }
    // Wake the audio up on the first click or key press (browser rule).
    ['pointerdown', 'keydown', 'touchstart'].forEach(ev => document.addEventListener(ev, function u() { audio(); document.removeEventListener(ev, u, true); }, true));

    /** One soft note: frequencies played together, starting `at` seconds from now. */
    function note(freqs, at, length, volume, type) {
        const c = audio(); if (!c || c.state !== 'running') return;
        const t = c.currentTime + (at || 0), g = c.createGain();
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(volume || 0.08, t + 0.015);
        g.gain.setValueAtTime(volume || 0.08, t + Math.max(0.02, length - 0.06));
        g.gain.exponentialRampToValueAtTime(0.0001, t + length);
        g.connect(c.destination);
        [].concat(freqs).forEach(f => { const o = c.createOscillator(); o.type = type || 'sine'; o.frequency.value = f; o.connect(g); o.start(t); o.stop(t + length + 0.02); });
    }
    /** Repeat a pattern every `every` ms until the returned stop() is called (or `max` ms pass). */
    function loop(play, every, max) {
        let stopped = false;
        const tick = () => { if (!stopped && on()) play(); };
        tick();
        const id = setInterval(tick, every);
        const cap = setTimeout(stop, max || 65000);
        function stop() { stopped = true; clearInterval(id); clearTimeout(cap); }
        return stop;
    }
    function throttled(kind, ms) { const now = Date.now(); if (now - (last[kind] || 0) < ms) return true; last[kind] = now; return false; }

    return {
        on, setOn,
        /** Someone is calling you: a bright two-bar melody, repeated. Returns stop(). */
        ringtone() {
            return loop(() => {
                [[659, 0], [784, 0.16], [988, 0.32], [784, 0.48], [659, 0.9], [784, 1.06], [988, 1.22], [1319, 1.38]]
                    .forEach(([f, at]) => note([f, f * 2], at, 0.22, 0.06, 'triangle'));
            }, 3000);
        },
        /** You are calling and waiting: the phone network's double ring. Returns stop(). */
        ringback() {
            return loop(() => { note([400, 450], 0, 0.4, 0.05); note([400, 450], 0.6, 0.4, 0.05); }, 3000);
        },
        /** A new notification: a soft two-note chime. */
        notify() {
            if (!on() || throttled('notify', 2000)) return;
            note(880, 0, 0.18, 0.07); note(1319, 0.14, 0.35, 0.06);
        },
        /** A new chat message: a short "pop". */
        message() {
            if (!on() || throttled('message', 1200)) return;
            note(740, 0, 0.09, 0.07); note(1109, 0.08, 0.14, 0.06);
        },
        /** The call ended or nobody answered: three falling beeps. */
        ended() {
            if (!on()) return;
            [[660, 0], [554, 0.22], [440, 0.44]].forEach(([f, at]) => note(f, at, 0.18, 0.06));
        },
        /** Someone joined your call. */
        joined() { if (on()) { note(660, 0, 0.12, 0.05); note(990, 0.1, 0.18, 0.05); } },
    };
})();
</script>
