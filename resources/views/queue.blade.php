<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Display Antrian</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

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
    </style>
</head>

<body>
    <div class="panel h-100">
        <div class="row g-0" style="height:100vh;">

            <!-- LEFT : QUEUE -->
            <div id="colAntrian" class="col-12 col-md-6 d-flex align-items-center justify-content-center bg-primary">
                <div class="w-100 text-center text-white p-4">

                    <div class="meta mb-3">
                        Puskesmas - Layar Panggilan Antrian
                    </div>

                    <!-- Sound Control -->
                    <div style="position:fixed;left:12px;top:12px;z-index:2000;min-width:220px" class="text-start">
                        <div class="small text-white-75 bg-opacity-25 p-2 rounded">
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <button id="enableSoundBtn" class="btn btn-sm btn-light" hidden>
                                        Enable Sound
                                    </button>
                                    <button id="testSoundBtn" class="btn btn-sm ms-1">
                                        <i class="bi bi-volume-up-fill text-white me-1" aria-hidden="true"></i>
                                    </button>

                                    <span id="soundStatus" class="small text-white-50">
                                        (muted)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queue Display -->
                    <div id="number" class="number">A-0</div>
                    <div id="pasien" class="pasien">-</div>
                </div>
            </div>

            <!-- RIGHT : YOUTUBE -->
            <div id="colYoutube" class="col-12 col-md-6 d-flex align-items-center justify-content-center">
                <iframe id="display-youtube"
                    src="https://www.youtube.com/embed/UrzIbQm7MCg?autoplay=1&mute=1&loop=1&playlist=UrzIbQm7MCg"
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

    @if (file_exists(public_path('build/manifest.json')))
    @vite('resources/js/app.js')
    @vite('resources/js/queue.js')
    @endif
</body>

</html>