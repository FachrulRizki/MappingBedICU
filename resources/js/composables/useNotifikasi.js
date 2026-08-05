/**
 * useNotifikasi — notifikasi suara polling.
 *
 * Strategi audio (berlapis):
 *   1. Web Audio API chime (butuh gesture unlock)
 *   2. SpeechSynthesis (butuh gesture unlock di Chrome)
 *   3. Unlock banner — muncul otomatis saat halaman load, memancing user klik
 *      sehingga AudioContext + speech ter-unlock sebelum notif tiba
 */
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

// ─────────────────────────────────────────────────────────────────────────────
// AUDIO CONTEXT — singleton, dibuat dari gesture pertama
// ─────────────────────────────────────────────────────────────────────────────
let _ctx            = null;
let _gestureHandled = false;

export const audioUnlocked = ref(false);
// Kontrol suara global — default aktif, diset dari AppLayout via toggleSound()
export const soundEnabled  = ref(true);

function isCtxReady() {
    return !!(_ctx && _ctx.state === 'running');
}

async function unlockAudio() {
    if (_gestureHandled && isCtxReady()) return;
    try {
        if (!_ctx || _ctx.state === 'closed') {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (AC) _ctx = new AC();
        }
        if (_ctx && _ctx.state === 'suspended') {
            await _ctx.resume();
        }
        if (isCtxReady()) {
            _gestureHandled = true;
            audioUnlocked.value = true;
        }
        // Pre-warm speech synthesis — speak silence agar tidak blocked saat notif tiba
        if (window.speechSynthesis) {
            window.speechSynthesis.getVoices();
            const dummy = new SpeechSynthesisUtterance(' ');
            dummy.volume = 0;
            dummy.rate   = 2;
            window.speechSynthesis.speak(dummy);
        }
    } catch (e) {
        console.warn('[notif] unlock audio:', e);
    }
}

function setupGestureListener() {
    const EVENTS = ['click', 'pointerdown', 'keydown', 'touchstart'];
    const handler = async () => {
        await unlockAudio();
        if (_gestureHandled) {
            EVENTS.forEach(ev => document.removeEventListener(ev, handler, true));
        }
    };
    EVENTS.forEach(ev => document.addEventListener(ev, handler, { capture: true, passive: true }));
}

if (typeof window !== 'undefined') {
    setupGestureListener();
    if (window.speechSynthesis) {
        window.speechSynthesis.getVoices();
        window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CHIME GENERATOR
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
    return voices.find(v => /gadis|andika/i.test(v.name) && v.localService)
        ?? voices.find(v => /gadis|andika/i.test(v.name))
        ?? voices.find(v => v.lang === 'id-ID' && v.localService)
        ?? voices.find(v => v.lang.startsWith('id') && v.localService)
        ?? voices.find(v => v.lang.startsWith('ms') && v.localService)
        ?? voices.find(v => v.localService)
        ?? voices.find(v => v.lang === 'id-ID')
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

        // Chrome bug: jika paused, resume dulu
        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
        }
        window.speechSynthesis.speak(utter);

        // Chrome bug: speech kadang stuck — kick-start setiap 5 detik
        const kickInterval = setInterval(() => {
            if (!window.speechSynthesis.speaking) {
                clearInterval(kickInterval);
                return;
            }
            window.speechSynthesis.pause();
            window.speechSynthesis.resume();
        }, 5000);
        utter.onend = () => clearInterval(kickInterval);
    } catch (e) { console.warn('[notif] speak ex:', e); }
}

// ─────────────────────────────────────────────────────────────────────────────
// PLAY FULL ANNOUNCEMENT — chime lalu ucapan
// ─────────────────────────────────────────────────────────────────────────────
function playAnnouncement(key, customText = null) {
    const chimeFn = CHIME_FN[key];
    const text    = customText || SPEECH_TEXT[key];

    let delayMs = 0;
    if (chimeFn && isCtxReady()) {
        delayMs = chimeFn() * 1000;
    }

    if (text) {
        const speechDelay = delayMs > 0 ? delayMs + 200 : 0;
        setTimeout(() => speak(text), speechDelay);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GLOBAL ALERT MODAL STATE
// ─────────────────────────────────────────────────────────────────────────────
const _alertModal = ref(null);

export function useNotifAlert() {
    return { alertModal: _alertModal, playAnnouncement };
}

// ─────────────────────────────────────────────────────────────────────────────
// COMPOSABLE UTAMA
// ─────────────────────────────────────────────────────────────────────────────
export function useNotifikasi() {
    const notifList  = ref([]);
    const showUnlock = ref(false);
    let pollTimer    = null;
    let startTimer   = null;
    let _id          = 1;

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

            doAutoRefresh();

            const ORDER  = ['ningnong', 'noning_external', 'noning_internal'];
            const sounds = notifs.map(n => n.sound);
            const top    = ORDER.find(s => sounds.includes(s)) ?? sounds[0];

            const topNotif     = notifs[0];
            const topNotifData = notifs.find(n => n.sound === top);
            const ttsText      = topNotifData?.message
                ? 'Perhatian. ' + topNotifData.message
                : null;

            // Play suara hanya jika user sudah aktifkan sound toggle
            if (top && soundEnabled.value) {
                playAnnouncement(top, ttsText);
            }

            _alertModal.value = {
                type     : topNotif.sound,
                message  : topNotif.message,
                count    : notifs.length,
                allNotifs: notifs,
            };
            setTimeout(() => { _alertModal.value = null; }, 30_000);

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

    async function handleUnlockClick() {
        await unlockAudio();
        showUnlock.value = false;
        speak('Notifikasi ICU siap.');
    }

    onMounted(() => {
        // Coba unlock audio sedini mungkin — berhasil jika user sudah pernah interact
        // (klik link navigasi ke halaman ini sudah cukup sebagai gesture)
        unlockAudio();

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
        showUnlock,
        handleUnlockClick,
        dismissNotif,
        _debug: {
            test() {
                console.log('[notif] test | ctx:', _ctx?.state, '| unlocked:', _gestureHandled);
                playAnnouncement('noning_internal');
                setTimeout(() => playAnnouncement('noning_external'), 4_000);
                setTimeout(() => playAnnouncement('ningnong'),        9_000);
            },
            testAlert() {
                _alertModal.value = {
                    type     : 'noning_external',
                    message  : 'TEST: Ada permintaan booking ICU dari pasien eksternal!',
                    count    : 1,
                    allNotifs: [{ type: 'test', sound: 'noning_external', message: 'Test notif' }],
                };
                setTimeout(() => { _alertModal.value = null; }, 8_000);
            },
            unlock   : unlockAudio,
            state    : () => ({ ctx: _ctx?.state ?? 'null', gestureHandled: _gestureHandled }),
            poll     : doPoll,
            voices   : () => window.speechSynthesis?.getVoices().map(v => `${v.lang} — ${v.name} (local:${v.localService})`),
            bestVoice: () => { const v = getBestVoice(); return v ? `${v.lang} — ${v.name}` : 'none'; },
            speak    : (t) => speak(t),
            announce : (k) => playAnnouncement(k),
        },
    };
}
