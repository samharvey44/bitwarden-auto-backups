<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk that vault exports are written to.
    |
    */

    'disk' => env('BACKUP_DISK', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Vaults Per User
    |--------------------------------------------------------------------------
    |
    | The most vaults each user may add, as each one runs the Bitwarden CLI on
    | this server. Set to 0 for no limit.
    |
    */

    'max_vaults_per_user' => (int) env('MAX_VAULTS_PER_USER', 10),

    /*
    |--------------------------------------------------------------------------
    | Working Disk
    |--------------------------------------------------------------------------
    |
    | The local disk used as scratch space for the Bitwarden CLI's per-run data
    | directories and for encrypted ZIP archives while they are being built.
    | Exports themselves are never written here, and nothing is kept once a
    | backup finishes.
    |
    */

    'working_disk' => 'bitwarden-cli',

    /*
    |--------------------------------------------------------------------------
    | Bitwarden CLI
    |--------------------------------------------------------------------------
    |
    | The path to the "bw" executable installed from the @bitwarden/cli npm
    | package, and how many seconds each CLI command may run before it is
    | treated as failed.
    |
    */

    'cli' => [
        'path' => env('BITWARDEN_CLI_PATH', base_path('node_modules/.bin/bw')),
        'timeout' => (int) env('BITWARDEN_CLI_TIMEOUT', 60),
    ],

];
