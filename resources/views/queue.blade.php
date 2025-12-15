<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Display Antrian</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicon: websocket logo SVG (preferred) and fallback to existing favicon.ico -->
    <!-- Add cache-busting query to force browsers to reload the updated icon -->
    <link rel="alternate icon" href="/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background: #fff;
            color: #000;
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
            font-size: 3vw;
            /* Prevent long patient names from wrapping to the next line.
               Use a single-line ellipsis so the display stays tidy. The
               full name will be available via the element's title (tooltip). */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            max-width: 100%;
        }

        .meta {
            opacity: .9
        }

        /* Larger, bolder POLI label below the pasien name */
        #poli {
            font-size: 4.5vw;
            font-weight: 700;
            margin-top: .5rem;
            letter-spacing: 0.02em;
            opacity: 0.95;
        }
    </style>
</head>

<body>
    <!-- Fullscreen start overlay (blocks page until user starts the display) -->
    <div id="enableOverlay" role="dialog" aria-modal="true"
        style="position:fixed;inset:0;z-index:3000;background:rgba(0,0,0,0.92);display:flex;align-items:center;justify-content:center;">
        <div style="text-align:center;color:#fff;padding:28px;max-width:520px;">
            <h2 style="font-size:1.9rem;margin-bottom:8px">Mulai Display Antrian</h2>
            <p style="opacity:.92;margin-bottom:10px">Tekan tombol <strong>Mulai</strong> untuk menampilkan layar
                antrian dan mengaktifkan suara.</p>
            <p style="opacity:.75;font-size:.92rem;margin-bottom:18px">(Gesture ini diperlukan agar pemutaran suara
                dan media dapat berjalan di sebagian besar browser.)</p>
            <button id="overlayEnableBtn" class="btn btn-lg btn-light text-dark" aria-label="Mulai"
                style="padding:.55rem 1.4rem;box-shadow:0 10px 30px rgba(0,0,0,.45);">Mulai</button>
            <div style="height:8px"></div>
            <small style="opacity:.65;display:block;margin-top:10px">Tekan sekali saja — layar akan otomatis
                mulai.</small>
        </div>
    </div>
    <div class="panel h-100">
        <div class="row g-0" style="height:100vh;">

            <!-- LEFT : QUEUE -->
            <div id="colAntrian" class="col-12 col-md-6 d-flex align-items-center justify-content-center">
                <div class="w-100 text-center text-black p-4">

                    <div class="meta mb-3">
                        Puskesmas - Layar Panggilan Antrian
                    </div>

                    <!-- Sound Control -->
                    <div style="position:fixed;left:12px;top:12px;z-index:2000;min-width:220px" class="text-start">
                        <div class="small text-black-75 bg-opacity-25 p-2 rounded">
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <button id="enableSoundBtn" class="btn btn-sm btn-light" hidden>
                                        Enable Sound
                                    </button>
                                    <button id="testSoundBtn" class="btn btn-sm ms-1">
                                        <i class="bi bi-volume-up-fill text-black me-1" aria-hidden="true"></i>
                                    </button>

                                    <span id="soundStatus" class="small text-black-50">
                                        (muted)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queue Display -->
                    <div id="number" class="number">A-0</div>
                    <div id="pasien" class="pasien">&nbsp;</div>
                    <div id="poli" class="meta" style="margin-top:.6rem;opacity:.95">&nbsp;</div>
                </div>
            </div>

            <!-- RIGHT : YOUTUBE -->
            <div id="colYoutube" class="col-12 col-md-6 d-flex align-items-center justify-content-center">
                <iframe id="display-youtube"
                    src="https://www.youtube.com/embed/Hbp6A8qcqAI?autoplay=1&mute=1&loop=1&playlist=Hbp6A8qcqAI"
                    frameborder="0" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen
                    style="width:100%;height:100%;border:0;background:#000">
                </iframe>
            </div>

        </div>
    </div>

    <!-- CONFIG UNTUK queue.js -->
    <script>
        window.__queue_channel = '{{ $channel ?? "queue-display" }}';
    </script>

    <script>
        // If URL contains ?videoId=..., update the YouTube iframe to use that id.
        (function() {
            try {
                const params = new URLSearchParams(window.location.search);
                const vid = params.get('videoId') || params.get('videoid');
                if (!vid) return;
                const iframe = document.getElementById('display-youtube');
                if (!iframe) return;
                // Preserve autoplay/mute/loop params and set playlist to the selected id for loop
                const q = new URLSearchParams({
                    autoplay: 1,
                    mute: 1,
                    loop: 1,
                    playlist: vid,
                    enablejsapi: 1
                });
                iframe.src = `https://www.youtube.com/embed/${encodeURIComponent(vid)}?${q.toString()}`;
            } catch (e) {
                console.warn('apply videoId param failed', e);
            }
        })();
    </script>

    @if (file_exists(public_path('build/manifest.json')))
    @vite('resources/js/app.js')
    @vite('resources/js/queue.js')
    @endif
    <script>
        // Overlay wiring: initialize audio on user gesture and hide overlay
        (function() {
            function fallbackInitAudio() {
                try {
                    if (!window.__audioCtx) window.__audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                    if (window.__audioCtx.state === 'suspended') window.__audioCtx.resume().catch(() => {});
                    window.__audioInited = true;
                } catch (e) {
                    console.warn('fallbackInitAudio', e);
                }
            }

            function enableAndClose() {
                try {
                    if (window.initAudio) window.initAudio();
                    else fallbackInitAudio();
                } catch (e) {
                    fallbackInitAudio();
                }
                try {
                    const s = document.getElementById('soundStatus');
                    if (s) s.textContent = '(enabled)';
                } catch (e) {}
                try {
                    const top = document.getElementById('enableSoundBtn');
                    if (top) top.setAttribute('disabled', 'true');
                } catch (e) {}
                try {
                    const ov = document.getElementById('enableOverlay');
                    if (ov) ov.style.display = 'none';
                } catch (e) {}
            }

            document.addEventListener('DOMContentLoaded', function() {
                try {
                    const overlay = document.getElementById('enableOverlay');
                    const btn = document.getElementById('overlayEnableBtn');
                    if (btn) btn.addEventListener('click', function(ev) {
                        ev.preventDefault();
                        enableAndClose();
                    });
                    if (overlay) overlay.addEventListener('click', function(ev) {
                        if (ev.target === overlay) enableAndClose();
                    });
                } catch (e) {
                    console.warn('overlay wiring failed', e);
                }
            });
        })();
    </script>
</body>

</html>