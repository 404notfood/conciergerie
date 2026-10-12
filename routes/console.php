<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:prepare-missions')->everyFifteenMinutes()->withoutOverlapping(15);

Schedule::command('app:remind-missions')->everyFifteenMinutes()->withoutOverlapping(15);
