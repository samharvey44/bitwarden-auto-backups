#!/bin/sh
set -e

# Cache the config, routes, views and events from this container's environment
# before running the command, so each start picks up any change to .env.
php artisan optimize --quiet

exec "$@"
