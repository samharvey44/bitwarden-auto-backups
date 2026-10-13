# Bitwarden Auto Backups

A tool for configuring automated backups of users' [Bitwarden](https://bitwarden.com) vaults.

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

## Services

| Service | URL / Host | Notes |
| --- | --- | --- |
| Application | http://localhost | |
| Vite dev server | http://localhost:5173 | Only while `sail npm run dev` is running |
| Mailpit | http://localhost:8025 | Catches all outgoing mail |
| MySQL | `localhost:3306` | Database `laravel`, user `sail`, password `password` |

If a port is already in use on your machine, override it in `.env` with `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_PORT` or `FORWARD_MAILPIT_DASHBOARD_PORT`, then run `sail up -d` again.
