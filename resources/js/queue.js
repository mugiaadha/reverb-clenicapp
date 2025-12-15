// Frontend queue listener (used when assets are built)
function updateDisplay(prefix, number, pasien, poli) {
    // show combined display like "A-31" in the large number element
    const combined = (prefix || 'A') + '-' + (number || 0);
    try {
        const n = document.getElementById('number');
        if (n) n.textContent = combined;
    } catch (e) { }
    try {
        const l = document.getElementById('pasien');
        if (l) l.textContent = String(pasien || '').toUpperCase();
    } catch (e) { }
    try {
        const p = document.getElementById('poli');
        if (p) p.textContent = poli ? ('POLI ' + String(poli).toUpperCase()) : '';
    } catch (e) { }
}

function numberToWordsIndo(n) {
    const units = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if (n < 0) return '';
    if (n <= 11) return units[n];
    if (n < 20) return units[n - 10] + ' belas';
    if (n < 100) {
        const puluh = Math.floor(n / 10);
        const rem = n % 10;
        const tens = (puluh === 1) ? 'sepuluh' : (units[puluh] + ' puluh');
        return tens + (rem ? ' ' + units[rem] : '');
    }
    if (n < 200) return 'seratus ' + numberToWordsIndo(n - 100);
    if (n < 1000) {
        const ratus = Math.floor(n / 100);
        const rem = n % 100;
        return units[ratus] + ' ratus' + (rem ? ' ' + numberToWordsIndo(rem) : '');
    }
    return String(n);
}

function makeAnnouncement(prefix, number, pasien, poli) {
    const numberWords = numberToWordsIndo(number);
    // Speak poli at the end of the announcement (after pasien)
    const poliPart = (poli && String(poli).trim()) ? `, ke poli ${poli}` : '';
    return `Antrian ${prefix} ${numberWords}, untuk pasien ${pasien}${poliPart}`;
}

// Fragment playback disabled — always use beep + TTS fallback.
// Increase delay so TTS doesn't overlap with the beep (prevent clipping)
const SPEECH_DELAY_MS = 600; // ms to wait after beep before speaking

function sleep(ms) { return new Promise(res => setTimeout(res, ms)); }


function speak(text) {
    try {
        if ('speechSynthesis' in window) {
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = 'id-ID';
            utter.volume = 1.0;
            if (typeof ttsVoice !== 'undefined' && ttsVoice) {
                try { utter.voice = ttsVoice; } catch (e) { }
            }
            utter.rate = 0.95;
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(utter);
        }
    } catch (e) {
        console.error('TTS error', e);
    }
}
// TTS / audio initialization for browsers that block audio without user gesture
let ttsReady = false;
let ttsVoice = null;
let audioCtx = null; // shared AudioContext created on user gesture
// default: ignore duplicates (dedupe ON). Set `window.__ignoreDuplicates = false` to allow duplicates.
if (typeof window !== 'undefined' && typeof window.__ignoreDuplicates === 'undefined') window.__ignoreDuplicates = true;
function initAudio() {
    // Resume AudioContext if suspended (autoplay policies)
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (Ctx) {
            if (!audioCtx) {
                audioCtx = new Ctx();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume().catch(() => { });
            }
        }
    } catch (e) {
        // ignore
    }

    if ('speechSynthesis' in window) {
        // load available voices and probe if necessary
        loadVoices();
        const voices = window.speechSynthesis.getVoices();
        if (!voices || voices.length === 0) {
            const probe = new SpeechSynthesisUtterance(' ');
            probe.lang = 'id-ID';
            probe.volume = 0.01;
            try { window.speechSynthesis.speak(probe); } catch (e) { }
        }
        ttsReady = true;
    }

    // Update UI if present
    try { const btn = document.getElementById('enableSoundBtn'); if (btn) btn.setAttribute('disabled', 'true'); } catch (e) { }
    try { const status = document.getElementById('soundStatus'); if (status) status.textContent = '(sound enabled)'; } catch (e) { }
    updateDebug({ ttsReady: true, last: 'initAudio' });
}

// expose utilities for inline pages to call (e.g., from blade overlay)
try {
    window.initAudio = initAudio;
    window.testSound = function () { try { testSound(); } catch (e) { console.warn('testSound missing', e); } };
} catch (e) { }

// Auto-initialize on first user click (covers most cases)
try {
    document.addEventListener('click', function onFirstClick() { try { initAudio(); } catch (e) { } }, { once: true });
} catch (e) { }

// Wire enable and test buttons when DOM is ready
try {
    document.addEventListener('DOMContentLoaded', function () {
        try {
            const btn = document.getElementById('enableSoundBtn');
            if (btn) btn.addEventListener('click', function (ev) { ev.preventDefault(); initAudio(); });
        } catch (e) { }
        try {
            const testBtn = document.getElementById('testSoundBtn');
            if (testBtn) testBtn.addEventListener('click', function (ev) { ev.preventDefault(); testSound(); });
        } catch (e) { }
    });
} catch (e) { }

// Debug helpers: update debug box if present and console log
function updateDebug({ echo = null, socketId = null, ttsReady: tts = null, last = null } = {}) {
    try {
        if (echo !== null) { try { const el = document.getElementById('dbgEcho'); if (el) el.textContent = echo ? 'yes' : 'no'; } catch (e) { } }
        if (socketId !== null) { try { const el = document.getElementById('dbgSocket'); if (el) el.textContent = socketId || '-'; } catch (e) { } }
        if (tts !== null) { try { const el = document.getElementById('dbgTts'); if (el) el.textContent = tts ? 'yes' : 'no'; } catch (e) { } }
        if (last !== null) { try { const el = document.getElementById('dbgLast'); if (el) el.textContent = last; } catch (e) { } }
    } catch (e) { }
    console.debug('queue:debug', { echo, socketId, ttsReady: tts, last });
}

// Play a short beep using WebAudio as a fallback check
async function playBeep(duration = 160, freq = 880) {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return false;
        // prefer shared audioCtx created on user gesture
        const ac = audioCtx || new Ctx();
        if (ac.state === 'suspended') {
            await ac.resume().catch(() => { });
        }
        const o = ac.createOscillator();
        const g = ac.createGain();
        o.type = 'sine';
        o.frequency.value = freq;
        // smooth ramp: start silent -> loud -> silent
        g.gain.value = 0.0;
        o.connect(g);
        g.connect(ac.destination);
        const now = ac.currentTime;
        // ramp quickly to audible level
        g.gain.setValueAtTime(0.0001, now);
        g.gain.linearRampToValueAtTime(0.28, now + 0.02);
        o.start(now);
        g.gain.linearRampToValueAtTime(0.0001, now + duration / 1000);
        o.stop(now + duration / 1000 + 0.02);
        // if we created a temporary context (no shared) don't keep it open
        if (!audioCtx) {
            setTimeout(() => { try { ac.close(); } catch (e) { } }, duration + 150);
        }
        return true;
    } catch (e) {
        console.warn('beep failed', e);
        return false;
    }
}

// Load voices and choose a good Indonesian voice when available
function loadVoices() {
    try {
        const voices = window.speechSynthesis.getVoices() || [];
        // prefer voice whose lang startsWith 'id' or name contains 'Indones'
        let chosen = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('id'))
            || voices.find(v => /indones/i.test(v.name))
            || voices[0] || null;
        ttsVoice = chosen;
        updateDebug({ ttsReady: !!chosen, last: 'voices-loaded' });
        const dbgVoiceEl = document.getElementById('dbgVoice'); if (dbgVoiceEl) dbgVoiceEl.textContent = chosen ? `${chosen.name} (${chosen.lang})` : '-';
        return voices;
    } catch (e) {
        console.warn('loadVoices failed', e);
        return [];
    }
}

// Test sound: beep + spoken sample
function testSound() {
    updateDebug({ last: 'test-sound' });
    try { initAudio(); } catch (e) { }
    playBeep(200, 880).then(ok => {
        if (ok) updateDebug({ last: 'beeped' });
        const sample = 'Ini suara percobaan. Antrian A tiga puluh tujuh, untuk pasien Budi, ke poli Umum.';
        try { speak(sample); updateDebug({ last: 'spoken-sample' }); } catch (e) { updateDebug({ last: 'speak-error' }); }
    });
}

async function onQueueCalled(e) {
    const prefix = (e.prefix ?? 'A');
    const number = (e.number ?? 0);
    const pasien = (e.pasien ?? '-');
    const poli = (e.poli ?? '');
    // stronger dedupe: normalize values and ignore duplicates within window
    try {
        const normPasien = String(pasien).trim().toLowerCase();
        const normNumber = parseInt(number, 10) || 0;
        const normPrefix = String(prefix).trim().toLowerCase();
        const normPoli = String(poli).trim().toLowerCase();
        const fp = `${normPrefix}:${normNumber}:${normPoli}:${normPasien}`;
        const now = Date.now();
        if (!window.__queue_event_last) window.__queue_event_last = { fp: null, at: 0 };
        const last = window.__queue_event_last;
        // logging for debugging duplicate deliveries
        try {
            const sid = (typeof Echo !== 'undefined' && Echo && typeof Echo.socketId === 'function') ? Echo.socketId() : (Echo && Echo.connector && Echo.connector.socketId ? Echo.connector.socketId() : null);
            console.debug('queue:event-received', { fp, raw: e, socketId: sid, now });
        } catch (logErr) { }
        // Only apply duplicate suppression if the global flag is enabled
        if ((typeof window.__ignoreDuplicates === 'undefined' || window.__ignoreDuplicates) && last.fp === fp && (now - last.at) < 3000) {
            updateDebug({ last: 'ignored-duplicate' });
            console.debug('queue:ignored-duplicate', { fp, since: now - last.at });
            return;
        }
        window.__queue_event_last.fp = fp;
        window.__queue_event_last.at = now;
    } catch (dedupErr) {
        console.warn('dedupe check failed', dedupErr);
    }
    updateDisplay(prefix, number, pasien, poli);
    const ann = makeAnnouncement(prefix, number, pasien, poli);
    updateDebug({ last: 'received event' });
    // Fragments removed: always use beep + TTS (ensure audio initialized)
    try {
        if (!ttsReady) {
            try { initAudio(); } catch (e) { }
        }
        updateDebug({ last: 'beep-before-speech' });
        // Fire the beep but don't block on it — speech should still occur even if beep fails.
        try {
            // slightly longer beep so it's perceptible and TTS starts after it finishes
            playBeep(220, 880).catch(beepErr => console.warn('beep failed', beepErr));
        } catch (e) {
            console.warn('playBeep threw', e);
        }
        // Always attempt to speak after a short delay so announcements are reliable.
        try {
            await sleep(SPEECH_DELAY_MS);
            // guard speaking to prevent duplicate TTS (extra safety)
            try {
                const speakFp = `${String(prefix).trim().toLowerCase()}:${parseInt(number, 10) || 0}:${String(poli).trim().toLowerCase()}:${String(pasien).trim().toLowerCase()}`;
                const nowSpeak = Date.now();
                if (!window.__queue_last_speak) window.__queue_last_speak = { fp: null, at: 0 };
                const ls = window.__queue_last_speak;
                if (ls.fp === speakFp && (nowSpeak - ls.at) < 5000) {
                    updateDebug({ last: 'skip-duplicate-speak' });
                    console.debug('queue:skip-duplicate-speak', { speakFp, since: nowSpeak - ls.at });
                } else {
                    speak(ann);
                    ls.fp = speakFp;
                    ls.at = nowSpeak;
                    updateDebug({ last: 'spoken-announcement' });
                }
            } catch (guardErr) {
                // fallback: speak normally
                speak(ann);
                updateDebug({ last: 'spoken-announcement' });
            }
        } catch (sErr) {
            console.warn('speak failed', sErr);
            updateDebug({ last: 'speak-failed' });
        }
    } catch (err) {
        console.error('announcement failed', err);
        updateDebug({ last: 'announcement-failed' });
    }
}

function whenEchoReady(callback, timeout = 5000) {
    const start = Date.now();
    (function check() {
        if (window.Echo) return callback(true);
        if (Date.now() - start > timeout) return callback(false);
        setTimeout(check, 200);
    })();
}

whenEchoReady(function (ready) {
    if (ready) {
        try {
            // Avoid double-subscribing: set global flag once subscribed
            if (window.__queue_subscribed) {
                updateDebug({ echo: true, last: 'already-subscribed' });
                return;
            }
            const chName = (window.__queue_channel || 'queue-display') + '.queue';
            Echo.channel(chName).listen('QueueCalled', (e) => onQueueCalled(e));
            window.__queue_subscribed = true;
            updateDebug({ echo: true, last: 'subscribed' });
            // poll for socket id for visibility
            const start = Date.now();
            const idCheck = setInterval(() => {
                try {
                    const sid = (Echo && typeof Echo.socketId === 'function') ? Echo.socketId() : (Echo && Echo.connector && Echo.connector.socketId ? Echo.connector.socketId() : null);
                    if (sid) {
                        updateDebug({ socketId: sid });
                        clearInterval(idCheck);
                        return;
                    }
                    if (Date.now() - start > 8000) {
                        updateDebug({ socketId: '-' });
                        clearInterval(idCheck);
                    }
                } catch (e) { clearInterval(idCheck); }
            }, 300);
        } catch (err) {
            console.error('Realtime listen failed', err);
            updateDebug({ echo: false, last: 'listen-failed' });
        }
    } else {
        console.warn('Echo not available - realtime disabled');
        updateDebug({ echo: false, last: 'echo-missing' });
    }
});

export { };
