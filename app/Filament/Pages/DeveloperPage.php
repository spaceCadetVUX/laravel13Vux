<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DeveloperPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon  = 'heroicon-o-code-bracket';
    protected static \UnitEnum|string|null  $navigationGroup = 'System';
    protected static ?string               $navigationLabel = 'Developer';
    protected static ?int                  $navigationSort  = 90;

    protected string $view = 'filament.pages.developer';

    public function getSystemInfo(): array
    {
        $dbVersion = '—';
        try {
            $dbVersion = match (config('database.default')) {
                'pgsql'  => DB::selectOne('SELECT version() AS v')->v,
                'mysql'  => DB::selectOne('SELECT VERSION() AS v')->v,
                default  => config('database.default'),
            };
            // shorten PostgreSQL verbose version
            if (str_contains($dbVersion, ',')) {
                $dbVersion = explode(',', $dbVersion)[0];
            }
        } catch (\Throwable) {}

        return [
            'PHP'           => '8.5',
            'Laravel'       => app()->version(),
            'Environment'   => config('app.env'),
            'App URL'       => config('app.url'),
            'Database'      => ucfirst(config('database.default')) . ' — ' . $dbVersion,
            'Cache'         => config('cache.default'),
            'Queue'         => config('queue.default'),
            'Session'       => config('session.driver'),
        ];
    }

    public function getStack(): array
    {
        return [
            'Backend'  => 'Laravel 13 · PHP 8.5 · PostgreSQL',
            'Frontend' => 'Nuxt 3 · TypeScript · Tailwind CSS',
            'Admin'    => 'Filament v3',
            'Search'   => 'Meilisearch + Laravel Scout',
            'Queue'    => 'Redis + Laravel Horizon',
            'AI / MCP' => 'Claude API · MCP Server (TypeScript)',
            'Workflow' => 'n8n · Supabase · Pancake',
        ];
    }
}
