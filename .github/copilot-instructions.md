## Purpose

Help AI coding agents quickly become productive in this Laravel + Reverb realtime chat project.

### Quick summary

-   Laravel application (classic `artisan` app structure) that serves a simple chat UI and broadcasts messages in realtime using a Reverb-compatible WebSocket backend.
-   Key runtime flow: `POST /chat/send` -> `App\Models\Message` persisted -> `App\Events\MessageSent` dispatched and broadcast on `chat` -> frontend `Echo.channel('chat')` receives `MessageSent` and updates UI.

### Most important files to read

-   `app/Http/Controllers/ChatController.php` — HTTP endpoints for the chat (`index`, `send`).
-   `app/Events/MessageSent.php` — event implements `ShouldBroadcast` and returns `new Channel('chat')`.
-   `app/Models/Message.php` — Eloquent model (fillable: `user`, `message`).
-   `resources/js/echo.js` — client Echo configuration; uses `broadcaster: 'reverb'` and `VITE_REVERB_*` env vars.
-   `resources/views/chat.blade.php` — UI, conditional loading of built Vite assets, fallback polling implementation and how the client expects the broadcast payload (`e.message`).
-   `config/reverb.php` — server and app-level Reverb configuration (host/port, scaling via Redis, app keys/options).
-   `package.json` / `vite.config.js` — front-end build and dev scripts (`npm run dev`, `npm run build`).
-   `.github/workflows/deploy.yml` — CI build + rsync deploy flow and required secrets (see deploy job for exact commands and secret names).

### Big-picture architecture and why

-   HTTP + Realtime: The app is a normal HTTP Laravel app with an additional realtime layer. Messages are stored in the database and also broadcast so connected clients update instantly.
-   Separation of concerns: controllers handle persistence & request validation, events encapsulate broadcasting concerns, Echo (client) handles WebSocket connectivity. The view `chat.blade.php` deliberately falls back to polling when Echo isn't available.
-   Reverb role: `reverb` acts as the Pusher-compatible websocket backend. Client and server rely on `VITE_REVERB_*` / `REVERB_*` env vars so the same code works in dev, CI, and production.

### Project-specific patterns and conventions

-   Events use `ShouldBroadcast` (alias imported as `ShouldBroadcast`) and return an array of `Channel` objects. Example: `return [ new Channel('chat') ];` (see `App\Events\MessageSent`).
-   Frontend expects the broadcast payload to include `message` (the saved `Message` model). The view listens to `'MessageSent'` and accesses `e.message`.
-   `resources/views/chat.blade.php` uses `@vite` only when `public/build/manifest.json` exists; this avoids attempting to connect to the Vite dev server in production environments.
-   Echo config: `resources/js/echo.js` constructs `wsPath` from `VITE_REVERB_PATH`. If you change websocket path or host, update both `config/reverb.php` (server) and the Vite env (`.env`, `VITE_REVERB_*`) so client and server align.
-   Polling fallback: the client polls `GET /api/messages` every 3s if Echo is not present — keep this in mind when changing API shape or payload formats.

### Environment variables to be aware of

-   Server-side (in `.env` / `config/reverb.php`): `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`, `REVERB_HOST`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_APP_ID`, Redis settings (`REDIS_URL`, `REDIS_HOST`, etc.) for scaling.
-   Client-side (Vite): `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_PATH`, `VITE_REVERB_SCHEME` — used in `resources/js/echo.js`.

### Local developer workflows & commands

-   Setup (PowerShell):

```
cp .env.example .env
composer install
npm ci
npm run dev    # starts Vite dev server
php artisan migrate
php artisan serve
```

-   Build for production:

```
npm ci; NODE_ENV=production npm run build
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
```

-   Tests: use `php artisan test` or `vendor/bin/phpunit` (CI uses `phpunit` from `vendor/bin` in workflows).

### Deployment notes (from `.github/workflows/deploy.yml`)

-   CI builds assets with Node 20 and uses `shivammathur/setup-php` for Composer steps.
-   The deployment step uses `rsync` over SSH (secrets required: `VPS_USER`, `VPS_HOST`, `VPS_PATH`, `SSH_PRIVATE_KEY`, optional `VPS_PORT`). The job also runs remote `php artisan` tasks (`migrate`, `config:cache`, `route:cache`, `view:cache`, `queue:restart`) and attempts non-interactive `sudo chown/chmod` for `www-data` on `storage` and `bootstrap/cache`.

### Troubleshooting & quick tips

-   If the client never connects, check `public/build/manifest.json` (view won't load Echo unless built). In dev run `npm run dev` and ensure `VITE_REVERB_*` envs point to the dev server or proxy.
-   To debug socket connections open browser devtools and inspect WebSocket frames and `Echo.socketId()` availability (client code waits up to ~5s for socket id).
-   For scaling or cross-instance broadcasting, `config/reverb.php` already includes a `scaling` section tied to Redis — ensure Redis networking and `REDIS_URL` are configured.
-   When changing broadcast event payloads, update both event class and `resources/views/chat.blade.php` listener expectations (`e.message` currently used).

### When editing JS/CSS or Echo config

-   Rebuild assets (`npm run build`) for production. For quick iteration use `npm run dev` and keep the Vite dev server running so `@vite` in templates resolves to the dev server.

If anything in these notes looks incomplete or you'd like more examples (e.g., a small PR template for Socket-related changes), tell me which section to expand.
