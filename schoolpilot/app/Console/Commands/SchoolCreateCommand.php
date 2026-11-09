<?php

namespace App\Console\Commands;

use App\Models\Platform\ProvisioningLog;
use App\Models\Platform\Tenant;
use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class SchoolCreateCommand extends Command
{
    protected $signature = 'school:create
        {name : School display name}
        {slug : Unique lowercase URL slug}
        {email : Email address for the first school administrator}';

    protected $description = 'Safely provision an isolated SQLite database for a school';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $slug = (string) $this->argument('slug');
        $email = strtolower(trim((string) $this->argument('email')));

        if ($name === '' || mb_strlen($name) > 150) {
            $this->components->error('School name must contain 1–150 characters.');
            return self::FAILURE;
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $this->components->error('Slug must use lowercase letters, numbers, and single hyphens only.');
            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('A valid administrator email address is required.');
            return self::FAILURE;
        }

        if (Tenant::query()->where('data->slug', $slug)->exists()) {
            $this->components->error("The school slug '{$slug}' is already registered.");
            return self::FAILURE;
        }

        $password = $this->secret('Set a temporary password for the school administrator (minimum 12 characters)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->components->error('Administrator password must be at least 12 characters.');
            return self::FAILURE;
        }

        $confirmation = $this->secret('Confirm administrator password');
        if (! is_string($confirmation) || ! hash_equals($password, $confirmation)) {
            $this->components->error('Passwords do not match.');
            return self::FAILURE;
        }

        $databaseName = $slug . '.sqlite';
        $tenantsDirectory = database_path('tenants');
        $databasePath = $tenantsDirectory . DIRECTORY_SEPARATOR . $databaseName;

        if (is_file($databasePath)) {
            $this->components->error('A tenant database file with this slug already exists. Resolve it before provisioning.');
            return self::FAILURE;
        }

        $previousDefault = DB::getDefaultConnection();
        $previousDatabase = Config::get('database.connections.tenant_sqlite.database');
        $tenant = null;
        $databaseCreated = false;

        try {
            if (! is_dir($tenantsDirectory) && ! mkdir($tenantsDirectory, 0750, true) && ! is_dir($tenantsDirectory)) {
                throw new \RuntimeException('Unable to create the tenant database directory.');
            }

            // Exclusive creation prevents two provisioning processes from sharing a new file.
            $handle = @fopen($databasePath, 'x');
            if ($handle === false) {
                throw new \RuntimeException('The tenant database file already exists or cannot be created.');
            }
            fclose($handle);
            $databaseCreated = true;
            @chmod($databasePath, 0640);

            Config::set('database.connections.tenant_sqlite.database', $databasePath);
            DB::purge('tenant_sqlite');

            $migrationExitCode = $this->call('migrate', [
                '--database' => 'tenant_sqlite',
                '--path' => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force' => true,
            ]);
            if ($migrationExitCode !== 0) {
                throw new \RuntimeException('Tenant database migrations did not complete successfully.');
            }

            $seederExitCode = $this->call('db:seed', [
                '--database' => 'tenant_sqlite',
                '--class' => \Database\Seeders\Tenant\DatabaseSeeder::class,
                '--force' => true,
            ]);
            if ($seederExitCode !== 0) {
                throw new \RuntimeException('Tenant baseline data could not be seeded.');
            }

            DB::setDefaultConnection('tenant_sqlite');
            DB::purge('tenant_sqlite');

            if (User::query()->where('email', $email)->exists()) {
                throw new \RuntimeException('The administrator email already exists in this tenant database.');
            }

            DB::table('settings')->updateOrInsert(
                ['key' => 'school_name'],
                ['value' => $name, 'updated_at' => now(), 'created_at' => now()],
            );

            User::query()->create([
                'name' => $name . ' Administrator',
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'school_admin',
                'is_active' => true,
            ]);

            DB::setDefaultConnection($previousDefault);
            DB::purge('tenant_sqlite');

            $tenant = Tenant::create([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'slug' => $slug,
                'db_name' => $databaseName,
                'status' => 'active',
            ]);

            ProvisioningLog::create([
                'school_id' => $tenant->id,
                'action' => 'create',
                'status' => 'success',
                'notes' => 'School database, baseline data, and first administrator created.',
            ]);

            $this->components->info('School provisioned successfully.');
            $this->line('School URL path: /school/' . $slug);
            $this->line('Administrator email: ' . $email);
            $this->line('Database: database/tenants/' . $databaseName);
            $this->warn('Deliver the temporary password to the administrator securely; it is not displayed or logged.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            DB::setDefaultConnection($previousDefault);
            DB::purge('tenant_sqlite');
            Config::set('database.connections.tenant_sqlite.database', $previousDatabase);

            if ($tenant) {
                try {
                    $tenant->domains()->delete();
                    $tenant->delete();
                } catch (Throwable) {
                    // Preserve the original provisioning failure.
                }
            }

            if ($databaseCreated && is_file($databasePath)) {
                @unlink($databasePath);
            }

            try {
                ProvisioningLog::create([
                    'school_id' => $tenant?->id,
                    'action' => 'create',
                    'status' => 'failed',
                    'notes' => 'Provisioning failed: ' . Str::limit($exception->getMessage(), 500),
                ]);
            } catch (Throwable) {
                // Logging must not hide the original provisioning failure.
            }

            $this->components->error('School provisioning failed: ' . $exception->getMessage());
            return self::FAILURE;
        } finally {
            DB::setDefaultConnection($previousDefault);
            DB::purge('tenant_sqlite');
            Config::set('database.connections.tenant_sqlite.database', $previousDatabase);
        }
    }
}
