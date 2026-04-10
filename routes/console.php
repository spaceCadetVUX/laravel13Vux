<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── SEO generation ────────────────────────────────────────────────────────────
// Run after midnight when traffic is lowest.
// withoutOverlapping() prevents concurrent runs if a previous job is still running.

Schedule::command('sitemap:generate')->daily()->at('02:00')->withoutOverlapping();
Schedule::command('llms:generate')->daily()->at('02:30')->withoutOverlapping();

// ── Maintenance ───────────────────────────────────────────────────────────────
Schedule::command('cart:prune')->daily()->at('03:00')->withoutOverlapping();
