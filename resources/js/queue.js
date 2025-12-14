// Frontend queue listener (used when assets are built)
function updateDisplay(prefix, number, loket) {
    const p = document.getElementById('prefix');
    const n = document.getElementById('number');
    const l = document.getElementById('loket');
    if (p) p.textContent = prefix;
    if (n) n.textContent = number;
    if (l) l.textContent = 'Loket ' + loket;
}

function numberToWordsIndo(n) {
    const units = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if (n < 0) return '';
    if (n <= 11) return units[n];
    if (n < 20) return units[n - 10] + ' belas';
    if (n < 100) {
        const puluh = Math.floor(n / 10);
        const rem = n % 10;
        return (puluh === 1 ? 'sepuluh' : units[puluh]) + (rem ? ' ' + units[rem] : '') + ' puluh'.replace(/\s+puluh$/, ' puluh');
    }
    if (n < 200) return 'seratus ' + numberToWordsIndo(n - 100);
    if (n < 1000) {
        const ratus = Math.floor(n / 100);
        const rem = n % 100;
        return units[ratus] + ' ratus' + (rem ? ' ' + numberToWordsIndo(rem) : '');
    }
    return String(n);
}

function makeAnnouncement(prefix, number, loket) {
    const numberWords = numberToWordsIndo(number);
    return `Antrian ${prefix} ${numberWords}, ke loket ${loket}`;
}

// Fragment playback disabled — always use beep + TTS fallback.
const SPEECH_DELAY_MS = 350; // ms to wait after beep before speaking

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
    const btn = document.getElementById('enableSoundBtn');
    const status = document.getElementById('soundStatus');
    if (btn) btn.setAttribute('disabled', 'true');
    if (status) status.textContent = '(sound enabled)';
    updateDebug({ ttsReady: true, last: 'initAudio' });
}

// expose utilities for inline pages to call (e.g., from blade overlay)
try {
    window.initAudio = initAudio;
    window.testSound = function () { try { testSound(); } catch (e) { console.warn('testSound missing', e); } };
} catch (e) { }

// Auto-initialize on first user click (covers most cases)
document.addEventListener('click', function onFirstClick() {
    try { initAudio(); } catch (e) { }
    document.removeEventListener('click', onFirstClick);
}, { once: true });

// Wire enable button if included in the view
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('enableSoundBtn');
    if (btn) btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        initAudio();
    });
    const testBtn = document.getElementById('testSoundBtn');
    if (testBtn) testBtn.addEventListener('click', (ev) => { ev.preventDefault(); testSound(); });
});

// Debug helpers: update debug box if present and console log
function updateDebug({ echo = null, socketId = null, ttsReady: tts = null, last = null } = {}) {
    try {
        if (echo !== null) {
            const el = document.getElementById('dbgEcho'); if (el) el.textContent = echo ? 'yes' : 'no';
        }
        if (socketId !== null) {
            const el = document.getElementById('dbgSocket'); if (el) el.textContent = socketId || '-';
        }
        if (tts !== null) {
            const el = document.getElementById('dbgTts'); if (el) el.textContent = tts ? 'yes' : 'no';
        }
        if (last !== null) {
            const el = document.getElementById('dbgLast'); if (el) el.textContent = last;
        }
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
    playBeep(140, 880).then(ok => {
        if (ok) updateDebug({ last: 'beeped' });
        const sample = 'Ini suara percobaan. Antrian A tiga puluh tujuh, ke loket lima.';
        try { speak(sample); updateDebug({ last: 'spoken-sample' }); } catch (e) { updateDebug({ last: 'speak-error' }); }
    });
}

async function onQueueCalled(e) {
    const prefix = e.prefix ?? 'A';
    const number = e.number ?? 0;
    const loket = e.loket ?? 0;
    updateDisplay(prefix, number, loket);
    const ann = makeAnnouncement(prefix, number, loket);
    updateDebug({ last: 'received event' });
    // Fragments removed: always use beep + TTS (ensure audio initialized)
    try {
        if (!ttsReady) {
            try { initAudio(); } catch (e) { }
        }
        updateDebug({ last: 'beep-before-speech' });
        // Fire the beep but don't block on it — speech should still occur even if beep fails.
        try {
            playBeep(120, 880).catch(beepErr => console.warn('beep failed', beepErr));
        } catch (e) {
            console.warn('playBeep threw', e);
        }
        // Always attempt to speak after a short delay so announcements are reliable.
        try {
            await sleep(SPEECH_DELAY_MS);
            speak(ann);
            updateDebug({ last: 'spoken-announcement' });
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
            const chName = (window.__queue_channel || 'queue-display') + '.queue';
            Echo.channel(chName).listen('QueueCalled', (e) => onQueueCalled(e));
            updateDebug({ echo: true });
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
