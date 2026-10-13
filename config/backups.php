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

    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Working Disk
    |--------------------------------------------------------------------------
    |
    | The local disk used as scratch space for the Bitwarden CLI's per-run data
    | directories and for exports before they are moved to the backup disk.
    | Nothing is kept here once a backup finishes.
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
