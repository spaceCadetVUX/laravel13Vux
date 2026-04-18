<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── SEO generation ────────────────────────────────────────────────────────────
// Run after midnight when traffic is lowest.
// withoutOverlapping() prevents double-runs; runInBackground() keeps scheduler tick free.

Schedule::command('sitemap:generate')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('llms:generate')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

// ── Maintenance ───────────────────────────────────────────────────────────────
Schedule::command('cart:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Clean up Livewire temporary uploads older than 24 hours.
Schedule::command('livewire:purge-temp', ['--hours' => 24])
    ->dailyAt('03:30')
    ->withoutOverlapping();

// ── Queue monitoring ──────────────────────────────────────────────────────────
// Saves Horizon metrics snapshot to DB every 5 min — required for the Horizon dashboard graphs.
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes();

// ── Search index rebuild ──────────────────────────────────────────────────────
// Full re-import every Sunday as a safety net in case incremental sync missed records.
Schedule::command('scout:import "App\Models\Product"')
    ->weekly()
    ->sundays()
    ->at('04:00');

Schedule::command('scout:import "App\Models\BlogPost"')
    ->weekly()
    ->sundays()
    ->at('04:30');
