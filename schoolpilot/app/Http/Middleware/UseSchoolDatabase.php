<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UseSchoolDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        $previousDefault = Config::get('database.default');
        $previousGuard = Auth::getDefaultDriver();
        $tenantDatabase = database_path('tenants/greenfield.sqlite');

        if (! is_file($tenantDatabase)) {
            abort(503, 'The school database is currently unavailable.');
        }

        Config::set('database.connections.tenant_sqlite.database', $tenantDatabase);
        Config::set('database.default', 'tenant_sqlite');
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');
        Auth::shouldUse('school');

        try {
            return $next($request);
        } finally {
            DB::purge('tenant_sqlite');
            Config::set('database.default', $previousDefault);
            DB::setDefaultConnection($previousDefault);
            Auth::forgetGuards();
            Auth::shouldUse($previousGuard);
        }
    }
}
