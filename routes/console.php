<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recompute challenge success rates, warm stats/leaderboard caches, prune
// expired reset tokens. Runs hourly (needs: php artisan schedule:work / cron).
Schedule::command('academy:refresh-derived-data')->hourly()->withoutOverlapping();

// Flush expired session rows from the database session table daily.
Schedule::command('session:prune')->daily();
