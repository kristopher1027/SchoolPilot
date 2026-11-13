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
        // Login endpoints do not need tenant resolution: the legacy URL only
        // redirects, while the universal login discovers the tenant at sign-in.
        if ($request->is('school/*/login')) {
            return $next($request);
        }

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

        if (! $tenant && $schoolSlug === 'greenfield') {
            // Backward-compatible mapping for the original school database.
            // New schools must have a central tenant registry record.
            $metadata = ['status' => 'active', 'db_name' => 'greenfield.sqlite'];
        } elseif (! $tenant) {
            abort(404, 'School not found.');
        } else {
            $metadata = json_decode($tenant->data ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        }

        if (($metadata['status'] ?? 'active') !== 'active') {
            abort(403, 'This school account is not active.');
        }

        $databaseName = $metadata['db_name'] ?? ($schoolSlug . '.sqlite');

        // Only allow a plain SQLite filename; path traversal and directories
        // are deliberately rejected.
        if (! is_string($databaseName) || ! preg_match('/^[a-zA-Z0-9_-]+\.sqlite$/', $databaseName)) {
            abort(503, 'The school database configuration is invalid.');
        }

        $tenantDirectory = (string) Config::get('tenancy.database.sqlite.path', database_path('tenants'));
        $tenantDatabase = rtrim($tenantDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $databaseName;

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

        // A session authenticated for one school must never be reused for another.
        $sessionSchoolKey = '_school_authenticated_slug';
        if ($request->session()->get($sessionSchoolKey) !== $schoolSlug) {
            Auth::guard('school')->logout();
            $request->session()->put($sessionSchoolKey, $schoolSlug);
        }

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
