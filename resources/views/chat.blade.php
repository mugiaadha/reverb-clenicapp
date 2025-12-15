<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Socket Reverb Chat</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 2rem;
            background: #fff;
            color: #000;
        }

        #messages {
            height: 340px;
            overflow: auto;
        }

        .connection-badge {
            font-size: .85rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h4 mb-0">Simple Chat</h1>
                    <span id="connStatus" class="badge bg-secondary connection-badge">Connecting...</span>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <ul id="messages" class="list-group list-group-flush">
                            <!-- messages appended here -->
                        </ul>
                    </div>
                    <div class="card-footer">
                        <form id="sendForm" class="row g-2">
                            <div class="col-12 col-sm-4">
                                <div class="input-group">
                                    <input id="user" class="form-control" placeholder="Your name" required />
                                    <button type="button" id="randomNameBtn" class="btn btn-outline-secondary"
                                        title="Random name">🎲</button>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <input id="message" class="form-control" placeholder="Message" required />
                            </div>
                            <div class="col-12 col-sm-2 d-grid">
                                <button type="submit" class="btn btn-primary">Send</button>
                            </div>
                        </form>
                    </div>
                </div>
                <p class="text-muted small mt-2">If real-time connection fails, the page will poll every 3s as a
                    fallback.</p>
            </div>
        </div>
    </div>

    {{-- Include Vite-built JS (loads Echo + app bootstrap).
         Only use the built assets when present to avoid attempting to
         connect to the Vite dev server when it is not running. --}}
    @if (file_exists(public_path('build/manifest.json')))
    @vite('resources/js/app.js')
    @endif

    <script>
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        // append message to UI (as list item)
        function appendMessage(m) {
            const el = document.getElementById('messages');
            const li = document.createElement('li');
            li.className = 'list-group-item';
            li.innerHTML = `<strong class="me-2">${escapeHtml(m.user)}</strong>${escapeHtml(m.message)}`;
            el.appendChild(li);
            el.scrollTop = el.scrollHeight;
        }

        function escapeHtml(s) {
            return (s + '').replace(/[&<>\"'`]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": "&#39;",
                "`": "&#96;"
            } [c]));
        }

        // attempt to connect using Echo (if built via npm)
        function startPolling() {
            async function poll() {
                try {
                    const res = await fetch('/api/messages');
                    if (res.ok) {
                        const data = await res.json();
                        document.getElementById('messages').innerHTML = '';
                        data.forEach(appendMessage);
                    }
                } catch (err) {
                    console.error(err);
                }
            }
            poll();
            return setInterval(poll, 3000);
        }

        function whenEchoReady(callback, timeout = 5000) {
            const start = Date.now();
            (function check() {
                if (window.Echo) return callback(true);
                if (Date.now() - start > timeout) return callback(false);
                setTimeout(check, 200);
            })();
        }

        let pollInterval = null;
        whenEchoReady(function(ready) {
            if (ready) {
                // register Echo channel
                try {
                    Echo.channel('chat')
                        .listen('MessageSent', (e) => {
                            appendMessage(e.message);
                            document.getElementById('connStatus').className =
                                'badge bg-success connection-badge';
                            document.getElementById('connStatus').textContent = 'Connected';
                        });
                    // also update status once socket id becomes available
                    (function waitForSocketId(timeout = 5000) {
                        const start = Date.now();
                        const idCheck = setInterval(() => {
                            try {
                                const sid = (Echo && typeof Echo.socketId === 'function') ? Echo
                                    .socketId() : (Echo && Echo.connector && Echo.connector.socketId ?
                                        Echo.connector.socketId() : null);
                                if (sid) {
                                    document.getElementById('connStatus').className =
                                        'badge bg-success connection-badge';
                                    document.getElementById('connStatus').textContent = 'Connected';
                                    clearInterval(idCheck);
                                    return;
                                }
                                if (Date.now() - start > timeout) {
                                    clearInterval(idCheck);
                                }
                            } catch (e) {
                                clearInterval(idCheck);
                            }
                        }, 250);
                    })();
                } catch (err) {
                    console.error('Echo connection failed:', err);
                    pollInterval = startPolling();
                    document.getElementById('connStatus').className = 'badge bg-danger connection-badge';
                    document.getElementById('connStatus').textContent = 'Realtime failed';
                }
            } else {
                // fallback to polling if Echo never appears
                pollInterval = startPolling();
                document.getElementById('connStatus').className = 'badge bg-warning connection-badge';
                document.getElementById('connStatus').textContent = 'Polling';
            }
        });

        // Fetch a random name from randomuser.me and fill the user input
        async function getRandomName() {
            try {
                const res = await fetch('https://randomuser.me/api/');
                if (!res.ok) throw new Error('Network response was not ok');
                const data = await res.json();
                const r = data.results && data.results[0];
                if (r && r.name) {
                    const name = `${r.name.first} ${r.name.last}`;
                    document.getElementById('user').value = name;
                }
            } catch (err) {
                console.error('Failed to fetch random name:', err);
            }
        }

        document.getElementById('randomNameBtn').addEventListener('click', (ev) => {
            ev.preventDefault();
            getRandomName();
        });

        // Auto-fetch a random name on page load if the user field is empty
        if (!document.getElementById('user').value) {
            getRandomName();
        }

        document.getElementById('sendForm').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            const user = document.getElementById('user').value.trim();
            const message = document.getElementById('message').value.trim();
            if (!user || !message) return;
            await fetch('/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    user,
                    message
                }),
            });
            document.getElementById('message').value = '';
        });
    </script>
</body>

</html>