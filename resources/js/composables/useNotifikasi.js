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
import { router } from '@inertiajs/vue3';

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
// ─────────────────────────────────────────────────────────────────────────────
function chimeTone(freq, startSec, durSec, vol = 0.55) {
    if (!isCtxReady()) return;
    const ctx   = _ctx;
    const osc1  = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'triangle';
    osc1.frequency.setValueAtTime(freq, ctx.currentTime + startSec);

    const osc2  = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(freq * 2, ctx.currentTime + startSec);

    osc1.connect(gain1); gain1.connect(ctx.destination);
    osc2.connect(gain2); gain2.connect(ctx.destination);

    const t = ctx.currentTime + startSec;
    gain1.gain.setValueAtTime(0, t);
    gain1.gain.linearRampToValueAtTime(vol,       t + 0.008);
    gain1.gain.exponentialRampToValueAtTime(0.001, t + durSec);
    gain2.gain.setValueAtTime(0, t);
    gain2.gain.linearRampToValueAtTime(vol * 0.25, t + 0.008);
    gain2.gain.exponentialRampToValueAtTime(0.001,  t + durSec * 0.7);

    osc1.start(t); osc1.stop(t + durSec + 0.05);
    osc2.start(t); osc2.stop(t + durSec * 0.7 + 0.05);
}

// Pola chime — return detik sampai speech dimulai
function chimeNoningInternal() {
    chimeTone(880, 0.00, 0.55);
    chimeTone(659, 0.55, 0.55);
    return 1.2;
}
function chimeNoningExternal() {
    chimeTone(659, 0.00, 0.38);
    chimeTone(880, 0.42, 0.38);
    chimeTone(659, 0.88, 0.38);
    chimeTone(880, 1.30, 0.45);
    return 1.85;
}
function chimeNingnong() {
    chimeTone(523, 0.00, 0.50, 0.6);
    chimeTone(784, 0.52, 0.60, 0.6);
    return 1.25;
}

const CHIME_FN = {
    noning_internal: chimeNoningInternal,
    noning_external: chimeNoningExternal,
    ningnong:        chimeNingnong,
};

// ─────────────────────────────────────────────────────────────────────────────
// SPEECH SYNTHESIS
// ─────────────────────────────────────────────────────────────────────────────
const SPEECH_TEXT = {
    noning_internal: 'Perhatian. Ada permintaan booking ICU dari ruang rawat inap.',
    noning_external: 'Perhatian. Ada permintaan booking ICU dari pasien eksternal.',
    ningnong:        'Perhatian. Bed ICU tersedia. Mohon segera antar pasien ke ICU.',
};

function getBestVoice() {
    const voices = window.speechSynthesis?.getVoices() ?? [];
    if (!voices.length) return null;

    // PRIORITAS LOKAL dulu — Google TTS (remote) diblokir Chrome dari async/polling context
    return voices.find(v => /gadis|andika/i.test(v.name) && v.localService)
        ?? voices.find(v => /gadis|andika/i.test(v.name))
        ?? voices.find(v => v.lang === 'id-ID' && v.localService)
        ?? voices.find(v => v.lang.startsWith('id') && v.localService)
        ?? voices.find(v => v.lang.startsWith('ms') && v.localService)
        ?? voices.find(v => v.localService)                // lokal apapun
        ?? voices.find(v => v.lang === 'id-ID')            // remote fallback terakhir
        ?? voices[0]
        ?? null;
}

function speak(text) {
    if (!window.speechSynthesis) return;
    try {
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        const voice = getBestVoice();
        if (voice) utter.voice = voice;
        utter.lang   = voice?.lang ?? 'id-ID';
        utter.rate   = 0.82;
        utter.pitch  = 0.95;
        utter.volume = 1.0;
        utter.onerror = (e) => console.warn('[notif] speech error:', e.error);
        utter.onstart = () => console.log('[notif] speaking:', text.substring(0, 40));
        window.speechSynthesis.speak(utter);
    } catch (e) { console.warn('[notif] speak ex:', e); }
}

// ─────────────────────────────────────────────────────────────────────────────
// PLAY FULL ANNOUNCEMENT — chime lalu ucapan
// text: teks dari server (nama pasien, ruang, dll) — fallback ke SPEECH_TEXT
// ─────────────────────────────────────────────────────────────────────────────
function playAnnouncement(key, customText = null) {
    const chimeFn = CHIME_FN[key];
    // Gunakan teks dari server jika ada, fallback ke default
    const text    = customText || SPEECH_TEXT[key];

    // Chime — hanya jika AudioContext ready
    let delayMs = 0;
    if (chimeFn && isCtxReady()) {
        delayMs = chimeFn() * 1000;
    }

    // Speech — SELALU jalankan, tidak tergantung AudioContext
    if (text) {
        const speechDelay = delayMs > 0 ? delayMs + 200 : 300;
        setTimeout(() => speak(text), speechDelay);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GLOBAL ALERT MODAL STATE — dipakai AppLayout untuk tampilkan modal bahaya
// ─────────────────────────────────────────────────────────────────────────────
const _alertModal = ref(null);

export function useNotifAlert() {
    return { alertModal: _alertModal };
}

// ─────────────────────────────────────────────────────────────────────────────
// COMPOSABLE UTAMA
// ─────────────────────────────────────────────────────────────────────────────
export function useNotifikasi() {
    const notifList = ref([]);
    let pollTimer   = null;
    let startTimer  = null;
    let _id         = 1;

    // Auto-refresh data halaman setelah notif masuk
    function doAutoRefresh() {
        try {
            router.reload({
                only          : ['antrian', 'summary', 'spriList', 'kamarKosong', 'statusKamarMap'],
                preserveScroll: true,
                preserveState : true,
            });
        } catch (_) {}
    }

    async function doPoll() {
        try {
            const res = await fetch('/icu/notifikasi/poll', {
                headers    : { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;

            const { notifs = [] } = await res.json();
            if (!notifs.length) return;

            // Auto-refresh data halaman
            doAutoRefresh();

            // Mainkan announcement (chime + ucapan) — tidak perlu cek isCtxReady di sini
            const ORDER  = ['ningnong', 'noning_external', 'noning_internal'];
            const sounds = notifs.map(n => n.sound);
            const top    = ORDER.find(s => sounds.includes(s)) ?? sounds[0];
            if (top) {
                // Cari message dari notif dengan sound tertinggi prioritasnya
                const topNotifData = notifs.find(n => n.sound === top);
                // Buat teks TTS yang informatif: "Perhatian. <message dari server>"
                const ttsText = topNotifData?.message
                    ? 'Perhatian. ' + topNotifData.message
                    : null;
                playAnnouncement(top, ttsText);
            }

            // Tampilkan danger alert modal di tengah layar
            const topNotif = notifs[0];
            _alertModal.value = {
                type     : topNotif.sound,
                message  : topNotif.message,
                count    : notifs.length,
                allNotifs: notifs,
            };
            setTimeout(() => { _alertModal.value = null; }, 30_000); // 30 detik

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
            pollTimer = setInterval(doPoll, 10_000);
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
            /** window.__notif.test() */
            test() {
                console.log('[notif] test | ctx:', _ctx?.state);
                playAnnouncement('noning_internal');
                setTimeout(() => playAnnouncement('noning_external'), 4_000);
                setTimeout(() => playAnnouncement('ningnong'),        9_000);
            },
            testAlert() {
                _alertModal.value = {
                    type    : 'noning_external',
                    message : 'TEST: Ada permintaan booking ICU dari pasien eksternal!',
                    count   : 1,
                    allNotifs: [{ type: 'test', sound: 'noning_external', message: 'Test notif' }],
                };
                setTimeout(() => { _alertModal.value = null; }, 8_000);
            },
            state    : () => ({ ctx: _ctx?.state ?? 'null', gestureHandled: _gestureHandled }),
            poll     : doPoll,
            voices   : () => window.speechSynthesis?.getVoices().map(v => `${v.lang} — ${v.name} (local:${v.localService})`),
            bestVoice: () => { const v = getBestVoice(); return v ? `${v.lang} — ${v.name}` : 'none'; },
            speak    : (t) => speak(t),
            announce : (k) => playAnnouncement(k),
        },
    };
}
