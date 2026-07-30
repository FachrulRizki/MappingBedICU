/**
 * useNotifikasi — notifikasi suara polling.
 *
 * Pola audio seperti pengumuman stasiun KAI:
 *   1. Chime/nada (Web Audio API)
 *   2. Jeda ~0.5 detik
 *   3. Pengumuman suara (Web Speech API)
 *
 * Support: Chrome 66+, Firefox 53+, Safari 14.1+, Edge 79+
 */

import { ref, onMounted, onUnmounted } from 'vue';

// ─────────────────────────────────────────────────────────────────────────────
// AUDIO CONTEXT — singleton, dibuat dari gesture pertama
// ─────────────────────────────────────────────────────────────────────────────
let _ctx            = null;
let _gestureHandled = false;

function isCtxReady() {
    return !!(_ctx && _ctx.state === 'running');
}

function createCtxFromGesture() {
    if (_ctx && _ctx.state !== 'closed') {
        try { _ctx.close(); } catch (_) {}
    }
    _ctx = null;
    try {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        _ctx = new AC();
    } catch (e) {
        console.warn('[notif] AudioContext gagal:', e);
    }
}

function setupGestureListener() {
    const EVENTS = ['click', 'pointerdown', 'keydown', 'touchstart'];
    const handler = () => {
        if (_gestureHandled && isCtxReady()) return;
        createCtxFromGesture();
        if (isCtxReady()) {
            _gestureHandled = true;
            EVENTS.forEach(ev => document.removeEventListener(ev, handler, true));
            // Pre-load voices saat audio unlock
            if (window.speechSynthesis) window.speechSynthesis.getVoices();
        }
    };
    EVENTS.forEach(ev => document.addEventListener(ev, handler, { capture: true, passive: true }));
}

if (typeof window !== 'undefined') {
    setupGestureListener();
    // Pre-load voices lebih awal
    if (window.speechSynthesis) {
        window.speechSynthesis.getVoices();
        window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CHIME GENERATOR — seperti bunyi bel stasiun
// Pakai triangle wave + sedikit overtone untuk suara lebih "metalik/bel"
// ─────────────────────────────────────────────────────────────────────────────
function chimeTone(freq, startSec, durSec, vol = 0.55) {
    if (!isCtxReady()) return;
    const ctx = _ctx;

    // Oscillator utama
    const osc1  = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'triangle';
    osc1.frequency.setValueAtTime(freq, ctx.currentTime + startSec);

    // Overtone (harmonik 2x frekuensi, volume rendah) untuk tekstur bel
    const osc2  = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(freq * 2, ctx.currentTime + startSec);

    osc1.connect(gain1); gain1.connect(ctx.destination);
    osc2.connect(gain2); gain2.connect(ctx.destination);

    const t = ctx.currentTime + startSec;

    // Envelope bel: attack cepat, decay lambat (khas bel logam)
    gain1.gain.setValueAtTime(0, t);
    gain1.gain.linearRampToValueAtTime(vol,     t + 0.008);   // attack 8ms
    gain1.gain.exponentialRampToValueAtTime(0.001, t + durSec); // decay alami

    gain2.gain.setValueAtTime(0, t);
    gain2.gain.linearRampToValueAtTime(vol * 0.25, t + 0.008);
    gain2.gain.exponentialRampToValueAtTime(0.001,  t + durSec * 0.7);

    osc1.start(t); osc1.stop(t + durSec + 0.05);
    osc2.start(t); osc2.stop(t + durSec * 0.7 + 0.05);
}

// ─────────────────────────────────────────────────────────────────────────────
// CHIME PATTERNS
// Pola: seperti pengumuman stasiun — nada pendek lalu pengumuman
// ─────────────────────────────────────────────────────────────────────────────

/**
 * NONING INTERNAL — "ding-dong" 2 nada (tinggi→rendah)
 * Total durasi: ~0.9 detik, ucapan mulai setelah 0.95 detik
 */
function chimeNoningInternal() {
    chimeTone(880, 0.00, 0.55);   // A5 — "NO"
    chimeTone(659, 0.55, 0.55);   // E5 — "ning"
    return 1.2; // ms sampai speech dimulai
}

/**
 * NONING EXTERNAL — "ding-dong ding-dong" 4 nada
 * Total durasi: ~1.7 detik, ucapan mulai setelah 1.75 detik
 */
function chimeNoningExternal() {
    chimeTone(659, 0.00, 0.38);
    chimeTone(880, 0.42, 0.38);
    chimeTone(659, 0.88, 0.38);
    chimeTone(880, 1.30, 0.45);
    return 1.85; // ms sampai speech dimulai
}

/**
 * NINGNONG — "dong-ding" 2 nada (rendah→tinggi), suara lebih "meriah"
 * Total durasi: ~1.0 detik, ucapan mulai setelah 1.1 detik
 */
function chimeNingnong() {
    chimeTone(523, 0.00, 0.50, 0.6);   // C5 rendah — "NING"
    chimeTone(784, 0.52, 0.60, 0.6);   // G5 tinggi — "nong"
    return 1.25; // ms sampai speech dimulai
}

const CHIME_FN = {
    noning_internal: chimeNoningInternal,
    noning_external: chimeNoningExternal,
    ningnong:        chimeNingnong,
};

// ─────────────────────────────────────────────────────────────────────────────
// SPEECH SYNTHESIS
// Teks pengumuman, diucapkan setelah chime selesai
// ─────────────────────────────────────────────────────────────────────────────
const SPEECH_TEXT = {
    noning_internal : 'Perhatian. Ada permintaan booking ICU dari ruang rawat inap.',
    noning_external : 'Perhatian. Ada permintaan booking ICU dari pasien eksternal.',
    ningnong        : 'Perhatian. Bed ICU tersedia. Mohon segera konfirmasi.',
};

function getBestVoice() {
    const voices = window.speechSynthesis?.getVoices() ?? [];

    // Prioritas: Gadis (id-ID) > Andika (id-ID) > voice id-ID lainnya > ms-MY > en-US lokal
    return voices.find(v => v.name.includes('Gadis'))
        ?? voices.find(v => v.name.includes('Andika'))
        ?? voices.find(v => v.lang === 'id-ID' && v.localService)
        ?? voices.find(v => v.lang === 'id-ID')
        ?? voices.find(v => v.lang.startsWith('id') && v.localService)
        ?? voices.find(v => v.lang.startsWith('id'))
        ?? voices.find(v => v.lang === 'ms-MY' && v.localService)
        ?? voices.find(v => v.lang.startsWith('ms'))
        ?? voices.find(v => v.lang === 'en-US' && v.localService)
        ?? voices.find(v => v.lang.startsWith('en'))
        ?? null;
}

function speak(text) {
    if (!window.speechSynthesis) return;
    window.speechSynthesis.cancel();

    const utter = new SpeechSynthesisUtterance(text);
    const voice = getBestVoice();

    if (voice) utter.voice = voice;
    utter.lang   = voice?.lang ?? 'id-ID';
    utter.rate   = 0.82;    // lebih lambat → lebih jelas dan tegas
    utter.pitch  = 0.95;    // sedikit lebih dalam, tidak cempreng
    utter.volume = 1.0;     // volume maksimal

    window.speechSynthesis.speak(utter);
}

// ─────────────────────────────────────────────────────────────────────────────
// PLAY FULL ANNOUNCEMENT — chime lalu ucapan (pola KAI)
// ─────────────────────────────────────────────────────────────────────────────
function playAnnouncement(key) {
    const chimeFn = CHIME_FN[key];
    const text    = SPEECH_TEXT[key];
    if (!chimeFn) return;

    // 1. Mainkan chime
    const delayMs = chimeFn() * 1000; // fungsi chime return berapa detik sampai speech

    // 2. Ucapkan setelah chime + jeda 200ms ekstra
    if (text) {
        setTimeout(() => speak(text), delayMs + 200);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// COMPOSABLE
// ─────────────────────────────────────────────────────────────────────────────
export function useNotifikasi() {
    const notifList = ref([]);
    let pollTimer   = null;
    let startTimer  = null;
    let _id         = 1;

    async function doPoll() {
        try {
            const res = await fetch('/icu/notifikasi/poll', {
                headers     : { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials : 'same-origin',
            });
            if (!res.ok) return;

            const { notifs = [] } = await res.json();
            if (!notifs.length) return;

            // Mainkan announcement (chime + ucapan)
            if (isCtxReady()) {
                const ORDER  = ['ningnong', 'noning_external', 'noning_internal'];
                const sounds = notifs.map(n => n.sound);
                const top    = ORDER.find(s => sounds.includes(s)) ?? sounds[0];
                if (top) playAnnouncement(top);
            }

            // Tampilkan toast
            for (const n of notifs) {
                const id = _id++;
                notifList.value.unshift({ id, ...n, ts: new Date() });
                setTimeout(() => { notifList.value = notifList.value.filter(x => x.id !== id); }, 8_000);
            }
            if (notifList.value.length > 5) notifList.value.splice(5);

        } catch { /* silent */ }
    }

    function dismissNotif(id) {
        notifList.value = notifList.value.filter(n => n.id !== id);
    }

    onMounted(() => {
        startTimer = setTimeout(() => {
            doPoll();
            pollTimer = setInterval(doPoll, 15_000);
        }, 1_000);
    });

    onUnmounted(() => {
        clearTimeout(startTimer);
        clearInterval(pollTimer);
    });

    return {
        notifList,
        dismissNotif,
        _debug: {
            /** Test semua suara: window.__notif.test() */
            test() {
                console.log('[notif] test | ctx:', _ctx?.state);
                playAnnouncement('noning_internal');
                setTimeout(() => playAnnouncement('noning_external'), 4_000);
                setTimeout(() => playAnnouncement('ningnong'),        9_000);
            },
            state   : () => ({ ctx: _ctx?.state ?? 'null', gestureHandled: _gestureHandled }),
            poll    : doPoll,
            voices  : () => window.speechSynthesis?.getVoices().map(v => `${v.lang} — ${v.name} (local:${v.localService})`),
            bestVoice: () => { const v = getBestVoice(); return v ? `${v.lang} — ${v.name}` : 'none'; },
            speak   : (t) => speak(t),
            announce: (k) => playAnnouncement(k),
        },
    };
}
