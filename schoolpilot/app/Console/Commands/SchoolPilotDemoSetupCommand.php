<?php

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SchoolPilotDemoSetupCommand extends Command
{
    protected $signature = 'schoolpilot:demo-setup';

    protected $description = 'Create the temporary Greenfield demo school or refresh its demo administrator password';

    public function handle(): int
    {
        if (app()->environment('production') && ! filter_var(env('SCHOOLPILOT_DEMO_MODE', false), FILTER_VALIDATE_BOOL)) {
            $this->components->error('Demo setup is disabled unless SCHOOLPILOT_DEMO_MODE=true.');
            return self::FAILURE;
        }

        $slug = (string) env('SCHOOLPILOT_DEMO_SCHOOL_SLUG', 'greenfield');
        $name = (string) env('SCHOOLPILOT_DEMO_SCHOOL_NAME', 'Greenfield Demo School');
        $email = strtolower((string) env('SCHOOLPILOT_DEMO_ADMIN_EMAIL', 'admin@schoolpilot-demo.test'));
        $password = (string) env('SCHOOLPILOT_DEMO_ADMIN_PASSWORD', '');

        if (strlen($password) < 12) {
            $this->components->error('SCHOOLPILOT_DEMO_ADMIN_PASSWORD must contain at least 12 characters.');
            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('data->slug', $slug)->first();

        if ($tenant && ($tenant->status !== 'active' || ! $tenant->db_name)) {
            $this->components->error('The configured demo school is not active or has no database.');
            return self::FAILURE;
        }

        // The original Greenfield demo predates central tenant registration.
        // Middleware still maps this slug to greenfield.sqlite, so startup must
        // backfill that same database rather than trying to provision a new one.
        $legacyDatabaseName = $slug === 'greenfield' ? 'greenfield.sqlite' : null;
        $legacyDatabasePath = $legacyDatabaseName
            ? rtrim((string) Config::get('tenancy.database.sqlite.path', database_path('tenants')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $legacyDatabaseName
            : null;

        if (! $tenant && (! $legacyDatabasePath || ! is_file($legacyDatabasePath))) {
            $exitCode = Artisan::call('school:create', [
                'name' => $name,
                'slug' => $slug,
                'email' => $email,
                '--demo' => true,
            ]);

            $this->output->write(Artisan::output());

            return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
        }

        $databaseName = $tenant ? basename($tenant->db_name) : $legacyDatabaseName;
        $databasePath = rtrim((string) Config::get('tenancy.database.sqlite.path', database_path('tenants')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $databaseName;

        if (! is_file($databasePath)) {
            $this->components->error('The configured demo tenant database does not exist.');
            return self::FAILURE;
        }

        $previousDefault = DB::getDefaultConnection();
        $previousDatabase = Config::get('database.connections.tenant_sqlite.database');

        try {
            Config::set('database.connections.tenant_sqlite.database', $databasePath);
            DB::purge('tenant_sqlite');
            DB::setDefaultConnection('tenant_sqlite');

            // Apply newly added tenant modules to existing school databases before seeding.
            $migrationExitCode = $this->call('migrate', [
                '--database' => 'tenant_sqlite',
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]);
            if ($migrationExitCode !== self::SUCCESS) {
                throw new \RuntimeException('Tenant database migrations could not be completed.');
            }

            // This seeder only inserts missing defaults; existing school data is preserved.
            $seedExitCode = $this->call('db:seed', [
                '--database' => 'tenant_sqlite',
                '--class' => \Database\Seeders\Tenant\DatabaseSeeder::class,
                '--force' => true,
            ]);

            if ($seedExitCode !== self::SUCCESS) {
                throw new \RuntimeException('Demo school baseline setup could not be completed.');
            }

            $updated = DB::connection('tenant_sqlite')
                ->table('users')
                ->where('email', $email)
                ->update(['password' => Hash::make($password), 'updated_at' => now()]);

            if ($updated === 0 && ! DB::connection('tenant_sqlite')->table('users')->where('email', $email)->exists()) {
                $this->components->error('The configured demo administrator account was not found.');
                return self::FAILURE;
            }

            $this->components->info('Demo school baseline data verified and demo administrator password refreshed.');
            return self::SUCCESS;
        } finally {
            DB::setDefaultConnection($previousDefault);
            DB::purge('tenant_sqlite');
            Config::set('database.connections.tenant_sqlite.database', $previousDatabase);
        }
    }
}
