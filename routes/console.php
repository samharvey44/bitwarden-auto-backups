<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('backups:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
