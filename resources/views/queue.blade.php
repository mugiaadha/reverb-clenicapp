<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Display Antrian</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: #0d6efd;
        color: #fff;
        height: 100vh;
        display: flex;
        align-items: center;
    }

    .panel {
        width: 100%;
    }

    .number {
        font-size: 10vw;
        font-weight: 700;
    }

    .pasien {
        font-size: 6vw;
    }

    .meta {
        opacity: .9
    }

    .center {
        text-align: center
    }
    </style>
</head>

<body>
    <div class="panel h-100">
        <div class="row g-0" style="height:100vh;">
            <!-- Left: Queue display -->
            <div id="colAntrian" class="col-12 col-md-6 d-flex align-items-center justify-content-center bg-primary">
                <div class="w-100 text-center text-white" style="padding:24px;">
                    <div class="meta mb-3">Puskesmas - Layar Panggilan Antrian</div>

                    <div style="position:fixed;left:12px;top:12px;z-index:2000;min-width:220px" class="text-start">
                        <div id="debugStatus" class="small text-white-75 bg-dark bg-opacity-25 p-2 rounded">
                            <div class="d-flex flex-column">
                                <div class="mb-2">
                                    <button id="enableSoundBtn" class="btn btn-sm btn-light text-dark">Enable
                                        Sound</button>
                                    <button id="testSoundBtn" class="btn btn-sm btn-outline-light text-dark ms-1">Test
                                        Sound</button>
                                </div>
                                <div>
                                    <span id="soundStatus" class="ms-2 small text-white-50">(muted)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="number" class="number">A-0</div>
                    <div id="pasien" class="pasien">-</div>
                </div>
            </div>

            <!-- Right: YouTube iframe -->
            <div id="colYoutube" class="col-12 col-md-6 d-flex align-items-center justify-content-center youtube-col">
                <div class="youtube-wrapper"
                    style="width:100%;height:100%;background:#000;display:flex;align-items:center;justify-content:center;">
                    <iframe id="display-youtube"
                        src="https://www.youtube.com/embed/UrzIbQm7MCg?autoplay=1&mute=1&loop=1&playlist=UrzIbQm7MCg&enablejsapi=1"
                        title="YouTube video" frameborder="0" allow="autoplay; encrypted-media; picture-in-picture"
                        allowfullscreen style="width:100%;height:100%;border:0;background:#000"></iframe>
                </div>
            </div>
        </div>
    </div>

    @if (file_exists(public_path('build/manifest.json')))
    @vite('resources/js/app.js')
    @endif
    <script>
    // expose the channel for the bundled JS to subscribe to
    window.__queue_channel = '{{ isset($channel) ? e($channel) : "queue-display" }}';
    // announcement dedupe / timing settings
    // default: ignore duplicates (dedupe ON).
    if (typeof window.__ignoreDuplicates === 'undefined') window.__ignoreDuplicates = true;
    window.__lastAnnouncement = {
        fingerprint: null,
        ts: 0
    };
    const ANNOUNCE_TTL_MS = 5000; // ignore identical announcements within 5s
    const SPEECH_DELAY_MS = 450; // delay TTS after chime (ms)
    // Hook overlay button to call initAudio exposed by the bundle and then hide overlay
    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('enableOverlay');
        const overlayBtn = document.getElementById('overlayEnableBtn');
        const topBtn = document.getElementById('enableSoundBtn');

        function enableAndHide() {
            try {
                if (window.initAudio) window.initAudio();
            } catch (e) {}
            if (overlay) overlay.style.display = 'none';
            try {
                if (topBtn) topBtn.setAttribute('disabled', 'true');
            } catch (e) {}
        }
        if (overlayBtn) overlayBtn.addEventListener('click', (ev) => {
            ev.preventDefault();
            enableAndHide();
        });
        // allow a single click anywhere on the overlay to enable
        if (overlay) overlay.addEventListener('click', (ev) => {
            if (ev.target === overlay) enableAndHide();
        });
    });

    // Layout controls: switch between Antrian / YouTube / Both
    function showAntrian() {
        try {
            document.getElementById('colYoutube').style.display = 'none';
        } catch (e) {}
        try {
            document.getElementById('colAntrian').style.display = 'flex';
        } catch (e) {}
    }

    function showYoutube() {
        try {
            document.getElementById('colAntrian').style.display = 'none';
        } catch (e) {}
        try {
            document.getElementById('colYoutube').style.display = 'flex';
        } catch (e) {}
    }

    function showBoth() {
        try {
            document.getElementById('colAntrian').style.display = 'flex';
        } catch (e) {}
        try {
            document.getElementById('colYoutube').style.display = 'flex';
        } catch (e) {}
    }

    // attach layout button listeners once DOM is ready and trigger default layout + enable click
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const la = document.getElementById('layoutAntrianBtn');
            const ly = document.getElementById('layoutYoutubeBtn');
            if (la) la.addEventListener('click', (ev) => {
                ev.preventDefault();
                showAntrian();
            });
            if (ly) ly.addEventListener('click', (ev) => {
                ev.preventDefault();
                showYoutube();
            });
        } catch (e) {}
        try {
            showBoth();
        } catch (e) {}
        // auto-click enable sound button after short delay
        try {
            setTimeout(() => {
                const tb = document.getElementById('enableSoundBtn');
                if (tb) tb.click();
            }, 150);
        } catch (e) {}

        // Wire Enable / Test sound buttons so clicks actually initialize audio
        try {
                const enableBtn = document.getElementById('enableSoundBtn');
                const testBtn = document.getElementById('testSoundBtn');
                const soundStatus = document.getElementById('soundStatus');

            function fallbackInitAudio() {
                try {
                    if (!window.__audioCtx) {
                        window.__audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                    }
                    if (window.__audioCtx.state === 'suspended') {
                        window.__audioCtx.resume().catch(() => {});
                    }
                    window.__audioInited = true;
                } catch (err) {
                    console.warn('fallbackInitAudio failed', err);
                }
            }

            if (enableBtn) enableBtn.addEventListener('click', (ev) => {
                try {
                    if (ev && ev.preventDefault) ev.preventDefault();
                    // prefer bundle-provided initializer if available
                    if (window.initAudio) {
                        try {
                            window.initAudio();
                        } catch (e) {
                            fallbackInitAudio();
                        }
                    } else {
                        fallbackInitAudio();
                    }
                    if (soundStatus) soundStatus.textContent = 'enabled';
                    try {
                        enableBtn.setAttribute('disabled', 'true');
                    } catch (e) {}
                } catch (err) {
                    console.error('enableSound click failed', err);
                }
            });

            if (testBtn) testBtn.addEventListener('click', (ev) => {
                try {
                    if (ev && ev.preventDefault) ev.preventDefault();
                    // prefer bundle test if present
                    if (window.testSound) {
                        try {
                            window.testSound();
                            return;
                        } catch (e) {
                            /* fallback below */
                        }
                    }
                    // fallback: ensure audio context is ready and play a short beep + TTS
                    fallbackInitAudio();
                    const ctx = window.__audioCtx;
                    if (ctx) {
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.type = 'sine';
                        o.frequency.value = 880;
                        o.connect(g);
                        g.connect(ctx.destination);
                        g.gain.setValueAtTime(0.0001, ctx.currentTime);
                        g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.01);
                        o.start();
                        setTimeout(() => {
                            try {
                                g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime +
                                    0.05);
                                o.stop();
                            } catch (e) {}
                        }, 250);
                    }
                    if ('speechSynthesis' in window) {
                        try {
                            const u = new SpeechSynthesisUtterance('Tes suara');
                            u.lang = 'id-ID';
                            u.rate = 0.95;
                            window.speechSynthesis.cancel();
                            window.speechSynthesis.speak(u);
                        } catch (e) {}
                    }
                } catch (err) {
                    console.error('testSound click failed', err);
                }
            });
                // dedupe setting removed (always on by default)
        } catch (e) {
            console.warn('Enable/Test wiring failed', e);
        }
    });

    // Basic UI update helper
    function updateDisplay(prefix, number, pasien) {
        document.getElementById('number').textContent = `${prefix}-${number}`;
        document.getElementById('pasien').textContent = pasien.toUpperCase();
    }

    // helper: play a short chime (uses bundle if available)
    function playChime() {
        try {
            if (window.playBeep) return window.playBeep();
            const ctx = window.__audioCtx;
            if (!ctx) return;
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.value = 880;
            o.connect(g);
            g.connect(ctx.destination);
            g.gain.setValueAtTime(0.0001, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.01);
            o.start();
            setTimeout(() => {
                try {
                    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.05);
                    o.stop();
                } catch (e) {}
            }, 220);
        } catch (e) {
            console.warn('playChime failed', e);
        }
    }

    // Indonesian number -> words (supports up to 999)
    function numberToWordsIndo(n) {
        const units = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh',
            'sebelas'
        ];
        if (n < 0) return '';
        if (n <= 11) return units[n];
        if (n < 20) return units[n - 10] + ' belas';
        if (n < 100) {
            const puluh = Math.floor(n / 10);
            const rem = n % 10;
            return (puluh === 1 ? 'sepuluh' : units[puluh]) + (rem ? ' ' + units[rem] : '') + ' puluh'.replace(
                /\s+puluh$/, ' puluh');
        }
        if (n < 200) return 'seratus ' + numberToWordsIndo(n - 100);
        if (n < 1000) {
            const ratus = Math.floor(n / 100);
            const rem = n % 100;
            return units[ratus] + ' ratus' + (rem ? ' ' + numberToWordsIndo(rem) : '');
        }
        return String(n);
    }

    // Compose announcement text
    function makeAnnouncement(prefix, number, pasien) {
        const numberWords = numberToWordsIndo(number);
        // e.g. "Antrian A tiga puluh tujuh, ke pasien lima"
        return `Antrian ${prefix} ${numberWords}, ke pasien ${pasien}`;
    }

    // Speak using Web Speech API
    function speak(text) {
        try {
            if ('speechSynthesis' in window) {
                const utter = new SpeechSynthesisUtterance(text);
                utter.lang = 'id-ID';
                // Slightly slower for clarity
                utter.rate = 0.95;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(utter);
            } else {
                // fallback: play a short beep if available
                console.warn('SpeechSynthesis not supported');
            }
        } catch (e) {
            console.error('TTS error', e);
        }
    }

    function onQueueCalled(e) {
        const prefix = e.prefix ?? 'A';
        const number = e.number ?? 0;
        const pasien = e.pasien ?? 0;
        updateDisplay(prefix, number, pasien);

        // dedupe identical announcements arriving multiple times
        try {
            const fp = `${prefix}-${number}-${String(pasien)}`;
            const now = Date.now();
            if (window.__lastAnnouncement && window.__lastAnnouncement.fingerprint === fp && (now - window
                    .__lastAnnouncement.ts) < ANNOUNCE_TTL_MS) {
                // duplicate within TTL, skip
                console.debug && console.debug('Skipping duplicate announcement', fp);
                return;
            }
            window.__lastAnnouncement = {
                fingerprint: fp,
                ts: now
            };
        } catch (err) {
            console.warn('announce dedupe error', err);
        }

        const ann = makeAnnouncement(prefix, number, pasien);
        // play chime (if audio enabled) then TTS after brief delay
        try {
            // ensure audio context exists if needed
            if (!window.__audioInited && window.initAudio) {
                try {
                    window.initAudio();
                } catch (e) {
                    /* ignore */
                }
            }
            playChime();
        } catch (e) {
            console.warn('chime play failed', e);
        }

        setTimeout(() => {
            try {
                speak(ann);
            } catch (e) {
                console.warn('speak failed', e);
            }
        }, SPEECH_DELAY_MS);
    }

    // Wait for Echo to appear (it is loaded via app.js if built)
    function whenEchoReady(callback, timeout = 5000) {
        const start = Date.now();
        (function check() {
            if (window.Echo) return callback(true);
            if (Date.now() - start > timeout) return callback(false);
            setTimeout(check, 200);
        })();
    }

    whenEchoReady(function(ready) {
        if (ready) {
            try {
                // subscribe to dynamic channel (e.g. 'tabaro.queue')
                const ch = (window.__queue_channel || 'queue-display') + '.queue';
                Echo.channel(ch).listen('QueueCalled', (e) => onQueueCalled(e));
            } catch (err) {
                console.error('Realtime listen failed', err);
            }
        } else {
            console.warn('Echo not available - realtime disabled');
        }
    });
    </script>
</body>

</html>