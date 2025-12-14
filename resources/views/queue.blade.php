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

        .loket {
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
    <div class="container panel">
        <div class="row justify-content-center">
            <div class="col-12 center">
                <!-- Fullscreen overlay to require a visible user gesture for audio initialization -->
                <div id="enableOverlay"
                    style="position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:3000;display:flex;align-items:center;justify-content:center;flex-direction:column;">
                    <div style="color:#fff;text-align:center;max-width:720px;padding:24px;">
                        <h2 style="margin-bottom:8px;">Enable Sound</h2>
                        <p style="opacity:.9;margin-bottom:18px;">Click the button to enable audio announcements on this
                            display. This is required by browser autoplay policies.</p>
                        <div>
                            <button id="overlayEnableBtn" class="btn btn-lg btn-light text-dark">Enable Sound</button>
                        </div>
                        <div style="margin-top:12px;opacity:.8;font-size:.9rem;color:#ddd">If you control the device
                            (kiosk), you can run Chrome with <code>--autoplay-policy=no-user-gesture-required</code> to
                            skip this step.</div>
                    </div>
                </div>

                <div class="meta mb-3">Puskesmas - Layar Panggilan Antrian</div>
                <div style="position:fixed;top:12px;right:12px;z-index:2000">
                    <button id="enableSoundBtn" class="btn btn-sm btn-light text-dark">Enable Sound</button>
                    <button id="testSoundBtn" class="btn btn-sm btn-outline-light text-dark ms-1">Test Sound</button>
                    <span id="soundStatus" class="ms-2 small text-white-50">(muted)</span>
                </div>

                <div style="position:fixed;left:12px;top:12px;z-index:2000;min-width:220px" class="text-start">
                    <div id="debugStatus" class="small text-white-75 bg-dark bg-opacity-25 p-2 rounded">
                        <div><strong>Echo:</strong> <span id="dbgEcho">-</span></div>
                        <div><strong>SocketId:</strong> <span id="dbgSocket">-</span></div>
                        <div><strong>TTS:</strong> <span id="dbgTts">no</span></div>
                        <div><strong>Voice:</strong> <span id="dbgVoice">-</span></div>
                        <div><strong>Last:</strong> <span id="dbgLast">idle</span></div>
                    </div>
                </div>
                <div id="prefix" class="h1">A</div>
                <div id="number" class="number">0</div>
                <div id="loket" class="loket">Loket 0</div>
            </div>
        </div>
    </div>

    @if (file_exists(public_path('build/manifest.json')))
    @vite('resources/js/app.js')
    @endif
    <script>
        // expose the channel for the bundled JS to subscribe to
        window.__queue_channel = '{{ isset($channel) ? e($channel) : "queue-display" }}';
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

        // Basic UI update helper
        function updateDisplay(prefix, number, loket) {
            document.getElementById('prefix').textContent = prefix;
            document.getElementById('number').textContent = number;
            document.getElementById('loket').textContent = 'Loket ' + loket;
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
        function makeAnnouncement(prefix, number, loket) {
            const numberWords = numberToWordsIndo(number);
            // e.g. "Antrian A tiga puluh tujuh, ke loket lima"
            return `Antrian ${prefix} ${numberWords}, ke loket ${loket}`;
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
            const loket = e.loket ?? 0;
            updateDisplay(prefix, number, loket);
            const ann = makeAnnouncement(prefix, number, loket);
            // speak announcement (no leading text to avoid overlapping with chime)
            speak(ann);
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