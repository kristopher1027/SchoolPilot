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
        $schoolSlug = $request->route('school');

        if (! is_string($schoolSlug)) {
            return $next($request);
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $schoolSlug)) {
            abort(404);
        }

        // Tenant metadata lives in the central SQLite database. Never derive
        // a filesystem path from unchecked request input.
        $tenant = DB::connection('central_sqlite')
            ->table('tenants')
            ->where('data->slug', $schoolSlug)
            ->first();

        if (! $tenant) {
            abort(404, 'School not found.');
        }

        $metadata = json_decode($tenant->data ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        if (($metadata['status'] ?? 'active') !== 'active') {
            abort(403, 'This school account is not active.');
        }

        $databaseName = $metadata['db_name'] ?? ($schoolSlug . '.sqlite');

        // Only allow a plain SQLite filename; path traversal and directories
        // are deliberately rejected.
        if (! is_string($databaseName) || ! preg_match('/^[a-zA-Z0-9_-]+\.sqlite$/', $databaseName)) {
            abort(503, 'The school database configuration is invalid.');
        }

        $tenantDatabase = database_path('tenants/' . $databaseName);

        if (! is_file($tenantDatabase)) {
            abort(503, 'The school database is currently unavailable.');
        }

        $previousDefault = Config::get('database.default');
        $previousGuard = Auth::getDefaultDriver();

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
