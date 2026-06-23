<?php

use App\Console\Commands\ExpireOldCarts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clear abandoned carts nightly.
Schedule::command(ExpireOldCarts::class)->dailyAt('03:00');
