<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Force HTTPS in Codespaces so cookies are saved correctly
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }

        // Resolve the school identity only after tenant middleware has selected
        // the active school's database connection.
        View::composer('tenant.layouts.app', function ($view): void {
            $settings = Schema::connection('tenant_sqlite')->hasTable('settings')
                ? DB::connection('tenant_sqlite')->table('settings')->pluck('value', 'key')
                : collect();

            $view->with([
                'tenantSchoolName' => $settings['school_name'] ?: 'Your School',
                'tenantSchoolMotto' => $settings['motto'] ?? null,
            ]);
        });
    }
}
