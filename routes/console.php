<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto re-assign panel-lawyer offers left unanswered past 7 days (EPIC G — Lebih Masa).
Schedule::command('agihan:lebih-masa')->dailyAt('07:00')->withoutOverlapping();
