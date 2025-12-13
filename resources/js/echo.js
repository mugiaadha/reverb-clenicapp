import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;
// Optional path for websocket endpoint, e.g. '/socket' or '/reverb'
const reverbPath = import.meta.env.VITE_REVERB_PATH ?? '';
const wsPath = reverbPath
    ? (reverbPath.startsWith('/') ? reverbPath : `/${reverbPath}`)
    : undefined;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    // pass path for websocket if configured
    wsPath: wsPath,
    path: wsPath,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
