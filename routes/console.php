<?php

use App\Console\Commands\RefreshStaleProfilesCommand;
use Illuminate\Support\Facades\Schedule;

// Assignment §4.A.3: run every 10 minutes; the command only enqueues
// profiles whose last_refreshed_at is null or older than 1 hour.
Schedule::command(RefreshStaleProfilesCommand::class)
    ->everyTenMinutes()
    ->withoutOverlapping(540);
