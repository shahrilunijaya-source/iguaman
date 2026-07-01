<?php

use Illuminate\Support\Facades\Schedule;

// PROC-18: a silently-failing scheduled command is invisible — log every failure.
$logFailure = fn (string $name) => fn () => logger()->error("scheduled command failed: {$name}");

// Auto re-assign panel-lawyer offers left unanswered past 7 days (EPIC G — Lebih Masa).
Schedule::command('agihan:lebih-masa')->dailyAt('07:00')->withoutOverlapping()
    ->onFailure($logFailure('agihan:lebih-masa'));

// W5 — expire Khidmat Nasihat grabs left unclaimed past 7 days (BUKA_GRAB -> LUPUT).
Schedule::command('grab:tamat-luput')->dailyAt('07:15')->withoutOverlapping()
    ->onFailure($logFailure('grab:tamat-luput'));

// W6 — monthly report of case attachments past the 7-year retention window.
// Report-only by default; disposal is a deliberate `lampiran:bersih-retensi --purge` run.
Schedule::command('lampiran:bersih-retensi')->monthlyOn(1, '02:00')->withoutOverlapping()
    ->onFailure($logFailure('lampiran:bersih-retensi'));
