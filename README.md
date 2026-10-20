# Bitwarden Auto Backups

Back up your [Bitwarden](https://bitwarden.com) vaults on a schedule, from the command line or an optional web interface. It runs in Docker, and the web interface is served privately to your devices over [Tailscale](https://tailscale.com).

> This project is not affiliated with or endorsed by Bitwarden.

## Development environment

The application is built with Laravel and runs entirely in Docker using [Laravel Sail](https://laravel.com/docs/sail). PHP, Composer and Node.js are **not** required on your machine; every command runs inside the Sail containers.

## Stack

- Laravel (PHP)
- MySQL
- Vue, built with Vite
- Tailwind CSS
- Mailpit (local mail catcher)

Versions are defined in `composer.json`, `package.json` and `compose.yaml`.

## Requirements

- [Docker](https://docs.docker.com/get-docker/) with Docker Compose
- Git
- On Windows: [WSL2](https://learn.microsoft.com/windows/wsl/install). Clone the project inside the WSL filesystem (e.g. `~/projects`), not under `/mnt/c`.

## Installation

1. Clone the repository and enter the directory:

    ```bash
    git clone <repository-url> bitwarden-auto-backups
    cd bitwarden-auto-backups
    ```

2. Install the Composer dependencies using a throwaway PHP container. This creates `vendor/bin/sail`:

    ```bash
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -e COMPOSER_HOME=/tmp/composer \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        composer:latest \
        composer install
    ```

3. Create your environment file. The defaults are already configured for the Sail containers:

    ```bash
    cp .env.example .env
    ```

4. Start the containers. The first run builds the app image, which takes a few minutes:

    ```bash
    ./vendor/bin/sail up -d
    ```

5. Generate the application key and run the migrations:

    ```bash
    ./vendor/bin/sail artisan key:generate
    ./vendor/bin/sail artisan migrate
    ```

6. Install the frontend dependencies and build the assets:

    ```bash
    ./vendor/bin/sail npm install
    ./vendor/bin/sail npm run build
    ```

The application is now available at [http://localhost](http://localhost).

### Optional: AI agent setup (Laravel Boost)

[Laravel Boost](https://laravel.com/docs/boost) is installed as a dev dependency. Its generated files (`CLAUDE.md`, `AGENTS.md`, `.mcp.json`, `boost.json`, `.claude/skills`, `.junie`) are gitignored, so generate them locally if you use an AI coding agent:

```bash
./vendor/bin/sail artisan boost:install
```

## Sail alias

Typing `./vendor/bin/sail` gets repetitive. Add this alias to your `~/.bashrc` or `~/.zshrc`:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

The examples below assume the alias.

## Day-to-day development

```bash
sail up -d              # Start the containers
sail npm run dev        # Start the Vite dev server with hot reloading
sail stop               # Stop the containers
sail down               # Stop and remove the containers (database data is kept)
```

Common commands:

```bash
sail artisan migrate    # Run migrations
sail artisan test       # Run the test suite
sail bin pint           # Format PHP code
sail composer require <package>
sail npm install <package>
sail shell              # Open a shell in the app container
```

Backups run on the queue and are triggered by the scheduler. `sail up` starts both alongside the app as the `queue` and `scheduler` services, so backups run whenever the containers are up. The queue worker picks up code changes automatically. To follow their output:

```bash
sail logs -f queue       # Queued backup jobs
sail logs -f scheduler   # Backups being queued as they become due
```

### Development services

| Service | URL / Host | Notes |
| --- | --- | --- |
| Application | http://localhost | |
| Vite dev server | http://localhost:5173 | Only while `sail npm run dev` is running |
| Mailpit | http://localhost:8025 | Catches all outgoing mail |
| MySQL | `localhost:3306` | Database `laravel`, user `sail`, password `password` |

If a port is already in use on your machine, override it in `.env` with `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_PORT` or `FORWARD_MAILPIT_DASHBOARD_PORT`, then run `sail up -d` again.

## Command-line setup

Everything needed to set up and run backups is available as Artisan commands, so the application can be used without the web interface. Each command prompts for anything it needs, and `--help` lists its options.

```bash
sail artisan users:create          # Create a user to own vaults
sail artisan vaults:create         # Add a Bitwarden vault to back up
sail artisan vaults:verify <id>    # Check a vault's credentials work, without backing it up
sail artisan vaults:list           # List vaults and their schedules
sail artisan vaults:edit <id>      # Change a vault's settings
sail artisan vaults:delete <id>    # Delete a vault, its backup history and its backup files (--keep-files keeps the files)
sail artisan backups:run <id>      # Queue a backup now, outside the schedule
sail artisan backups:list <id>     # List a vault's recent backups, their files and any errors
```

A vault needs its server's URL (`https://vault.bitwarden.com` for the US cloud, `https://vault.bitwarden.eu` for the EU cloud, or your self-hosted server's), a [personal API key](https://bitwarden.com/help/personal-api-key/) (Settings › Security › Keys › View API key in the web vault) and the account's master password. Backups can be saved in either or both of these formats:

- **Encrypted JSON**, protected by an export password and importable into any Bitwarden account.
- **JSON**, unencrypted and importable into other password managers. It is saved inside an AES-256 encrypted ZIP when given a password, or as a plain JSON file when not.

Each vault is backed up daily, weekly or monthly, and keeps its most recent successful backups, 30 by default. After each successful backup, older ones beyond that are deleted, along with their files, so keep more if you need a longer history. Failed backups don't count towards the limit. Both are set when adding a vault, with `--frequency` and `--retain` on the command line, and can be changed later. New vaults are backed up the next time the scheduler runs, then on their schedule.

> [!IMPORTANT]
> Backups are made with the Bitwarden CLI's `export` command, so they contain the same as an export from Bitwarden itself. They **do not include file attachments**, or items owned by **organizations** you are a member of, only your individual vault.

When a backup fails, the vault's owner is notified. To avoid a notification for every failed run, they are only notified again once one of the vault's backups has succeeded. Notifications are currently sent by email, using the `MAIL_*` settings in `.env`, so set these up to receive them, including when using only the command line.

Failure notifications can't tell you when backups stop running altogether, for example because the server is off. For that, set `BACKUP_HEARTBEAT_URL` to the ping URL of a heartbeat monitoring service, one that alerts you when it stops receiving requests. It's requested with a `GET` after every successful backup, so set the service's expected interval to match how often your vaults are backed up. With several vaults, a successful backup of any of them counts.

Users are only created with `users:create` by default. To let people create their own accounts from a register page in the web interface, set `REGISTRATION_ENABLED=true`. Registrations are limited to five an hour from each IP address, and each user can add up to 10 vaults, which `MAX_VAULTS_PER_USER` changes (`0` for no limit).

> [!WARNING]
> Every vault makes this server connect to the Bitwarden server URL it is given. If you enable registration on a publicly reachable instance, anyone who signs up can point a vault at addresses on your private network. Block the queue worker from connecting to private, loopback and link-local addresses at the network level, for example with a firewall rule or an outbound proxy such as [smokescreen](https://github.com/stripe/smokescreen).

### Running without prompts

Pass `--no-interaction` and give settings as options. Secrets are never accepted as options, as they would be left in your shell history, so they are read from environment variables instead:

| Variable | Used by | Value |
| --- | --- | --- |
| `USER_PASSWORD` | `users:create` | The user's password |
| `VAULT_CLIENT_ID` | `vaults:create`, `vaults:edit` | The API key's `client_id` |
| `VAULT_CLIENT_SECRET` | `vaults:create`, `vaults:edit` | The API key's `client_secret` |
| `VAULT_MASTER_PASSWORD` | `vaults:create`, `vaults:edit` | The master password |
| `VAULT_ENCRYPTED_JSON_EXPORT_PASSWORD` | `vaults:create`, `vaults:edit` | The password encrypted JSON backups are protected with |
| `VAULT_JSON_EXPORT_PASSWORD` | `vaults:create`, `vaults:edit` | The password JSON backups are zipped with. Leave unset for plain JSON |

`sail artisan` does not pass your shell's environment variables into the container, so set them inside it instead:

```bash
sail shell
read -rs VAULT_CLIENT_SECRET && export VAULT_CLIENT_SECRET   # Repeat for each secret
php artisan vaults:create --no-interaction --user=you@example.com --name=Personal --server-url=https://vault.bitwarden.com --format=encrypted_json
```

## Production

Production runs in Docker with [compose.production.yaml](compose.production.yaml), which is separate from the Sail development setup. It runs the queue worker, the scheduler and MySQL, and nothing is reachable from outside the machine. The web interface is optional, only runs with the `web` profile, and is only reachable over [Tailscale](https://tailscale.com). Images are built on the machine itself. Docker with Docker Compose is the only requirement.

The commands below use this alias:

```bash
alias backups='docker compose -f compose.production.yaml'
```

### First-time setup

1. Clone the repository and create the environment file:

    ```bash
    git clone <repository-url> bitwarden-auto-backups
    cd bitwarden-auto-backups
    cp .env.production.example .env
    ```

2. Build the image, then generate the application key and paste it into `APP_KEY` in `.env`. Fill in the other required settings too, such as the database password. To use the web interface, set it up as described in [Web interface](#web-interface) first.

    ```bash
    backups build
    backups run --rm --no-deps worker php artisan key:generate --show
    ```

3. Start it. The database tables are created on the first start, and any new migrations run each time it starts:

    ```bash
    backups up -d
    ```

4. Create a user and add a vault, answering the prompts. To run without prompts, set the secrets listed in [Running without prompts](#running-without-prompts) in your shell and pass them through by name, such as `backups exec -e VAULT_MASTER_PASSWORD worker php artisan vaults:create --no-interaction ...`, so they never appear in the command itself:

    ```bash
    backups exec -it worker php artisan users:create
    backups exec -it worker php artisan vaults:create
    backups exec -it worker php artisan vaults:verify 1
    ```

Follow what it's doing with `backups logs -f worker scheduler`.

### Web interface

The web interface joins your tailnet as its own machine, so only your devices on Tailscale can reach it, at `https://<TS_HOSTNAME>.<your tailnet>.ts.net`. Nothing is published on the server itself. To set it up:

1. In the Tailscale admin console, turn on MagicDNS and HTTPS certificates (DNS page).
2. Create an auth key (Settings › Keys). Don't make it ephemeral, or the machine is removed whenever it goes offline. Giving it a tag stops the machine's login expiring.
3. In `.env`, uncomment `COMPOSE_PROFILES=web`, and set `TS_AUTHKEY` to the key, `TS_HOSTNAME` to the machine name you want (each project on the server needs its own), and `APP_URL` to the address it gives.
4. Build and start it:

    ```bash
    backups build
    backups up -d
    ```

`COMPOSE_PROFILES=web` makes every command include the web interface, so building and updating always keep it in step with the rest of the app. The machine's identity is kept in the `tailscale` volume, so the auth key is only needed the first time. The first visit can take a few seconds while Tailscale issues the HTTPS certificate.

### Storing backups offsite

Backups are kept in the `storage` volume by default, so they are lost along with the machine. To store them in S3-compatible storage such as Cloudflare R2 or Backblaze B2 instead, set `BACKUP_DISK=s3` and fill in the `AWS_*` settings in `.env`, then restart with `backups up -d`. For anything other than Amazon S3, `AWS_ENDPOINT` is your provider's S3 API address, such as `https://<account id>.r2.cloudflarestorage.com` for R2.

### Updating

```bash
git pull
backups pull --ignore-buildable
backups build --pull
backups up -d
```

This also picks up the latest Tailscale release and any security updates to the PHP, Node and nginx images the app is built on, so run it now and then even when the code hasn't changed. Restart with `backups up -d` after any change to `.env`, too. The worker is given up to 7 minutes to finish a running backup before it stops.
