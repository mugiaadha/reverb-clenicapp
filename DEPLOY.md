**Deployment to VPS (GitHub Actions)**

This project contains a GitHub Actions workflow that builds frontend assets and deploys the repository to a VPS using rsync+SSH.

Files added:

-   `.github/workflows/deploy.yml` — builds assets and deploys on push to `main` or manual dispatch.

Secrets required (set these in your repository Settings → Secrets → Actions):

-   `SSH_PRIVATE_KEY` — your private SSH key (PEM/OpenSSH format). **Do not commit keys to the repo.**
-   `VPS_HOST` — the IP or hostname of your VPS.
-   `VPS_PORT` — SSH port (usually `22`).
-   `VPS_USER` — username on the VPS (e.g. `deploy` or `ubuntu`).
-   `VPS_PATH` — absolute path to the project directory on the VPS (e.g. `/var/www/socket-reverb`).

Server setup (summary):

1. On your machine, create an SSH keypair or use an existing one:

    ```bash
    ssh-keygen -t ed25519 -C "deploy@yourdomain" -f ~/.ssh/reverb_deploy
    ```

2. Add the public key (`~/.ssh/reverb_deploy.pub`) to `~/.ssh/authorized_keys` for the `VPS_USER` on the server.

3. Add the private key contents to the `SSH_PRIVATE_KEY` secret in GitHub.

4. Ensure the `VPS_PATH` directory exists and is writable by your deployment user, and that `composer` and PHP are available on the server.

Notes about the workflow behavior:

-   The workflow runs `npm ci` and `npm run build` on the runner, then transfers repository files (excluding `vendor`, `node_modules`, `.env`, etc.) to the VPS using `rsync` over SSH.
-   After transfer, it connects to the VPS and runs `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, and a set of common cache commands. These commands are best-effort (they use `|| true` so the workflow doesn't always fail on non-critical commands) — you can edit them to match your server's requirements.
-   The workflow does not upload your `.env` file. Keep environment variables on the server or via your host provider's configuration.

Security reminders:

-   Do not store private keys in the repository. Use GitHub Secrets.
-   Limit the private key's permissions (e.g. use a dedicated `deploy` user with restricted SSH `authorized_keys` options).

Customizations you may want:

-   Run tests before deployment.
-   Build on a different branch or only on tagged releases.
-   Use a safer deployment strategy (atomic deploys using symlinks or a CI/CD tool like Envoyer, Capistrano, or deployer.org).

If you want, I can update the workflow to:

-   Upload built `vendor` directory instead of running `composer` on server.
-   Use a different deploy method (SCP + remote script, or an action like `appleboy/scp-action` + `appleboy/ssh-action`).

**_End_**
