<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto re-assign panel-lawyer offers left unanswered past 7 days (EPIC G — Lebih Masa).
Schedule::command('agihan:lebih-masa')->dailyAt('07:00')->withoutOverlapping();

// W6 — monthly report of case attachments past the 7-year retention window.
// Report-only by default; disposal is a deliberate `lampiran:bersih-retensi --purge` run.
Schedule::command('lampiran:bersih-retensi')->monthlyOn(1, '02:00')->withoutOverlapping();
