<?php

namespace App\Console\Commands;

use App\Models\Platform\Tenant;
use App\Models\Platform\ProvisioningLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class SchoolCreateCommand extends Command
{
    protected $signature = 'school:create {name} {slug}';
    protected $description = 'Provision a new school database and seed it';

    public function handle()
    {
        $name = $this->argument('name');
        $slug = Str::slug($this->argument('slug'));

        $this->info("Creating school: {$name} (slug: {$slug})...");

        if (Tenant::where('id', $slug)->exists()) {
            $this->error("School with slug '{$slug}' already exists!");
            return;
        }

        // Create tenant in central DB
        $tenant = Tenant::create([
            'id' => $slug,
            'name' => $name,
            'slug' => $slug,
            'db_name' => 'schoolpilot_tenant_' . $slug,
            'status' => 'trial',
        ]);

        $tenant->domains()->create([
            'domain' => "{$slug}.schoolpilot.test",
        ]);

        try {
            // 1. Manually create the SQLite file
            $tenantsDir = database_path('tenants');
            if (!file_exists($tenantsDir)) {
                mkdir($tenantsDir, 0755, true);
            }
            $dbPath = $tenantsDir . '/' . $tenant->id . '.sqlite';
            if (!file_exists($dbPath)) {
                touch($dbPath);
            }

            // 2. Tell Laravel to use this new file as the default database
            Config::set('database.connections.sqlite.database', $dbPath);
            
            // 3. FORCE Laravel to disconnect from central DB and reconnect to the new file!
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            // 4. Run migrations directly
            $this->call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // 5. Run Seeders
            $this->call('db:seed', [
                '--class' => 'App\Models\Tenant\TenantDatabaseSeeder',
                '--force' => true,
            ]);
            
            // 6. Create Admin
            \App\Models\Tenant\User::create([
                'name' => $name . ' Admin',
                'email' => 'admin@' . strtolower(str_replace(' ', '', $name)) . '.com',
                'password' => bcrypt('password'),
                'role' => 'school_admin',
            ]);

            // 7. Restore the central connection
            Config::set('database.connections.sqlite.database', database_path('database.sqlite'));
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            ProvisioningLog::create([
                'school_id' => $tenant->id,
                'action' => 'create',
                'status' => 'success',
                'notes' => "School provisioned successfully."
            ]);

            $this->info("✅ School created successfully!");
            $this->info("Domain: {$slug}.schoolpilot.test");
            $this->info("Database: database/tenants/{$slug}.sqlite");
            $this->info("Admin Email: admin@" . strtolower(str_replace(' ', '', $name)) . ".com");
            $this->info("Admin Password: password");

        } catch (\Exception $e) {
            // Restore central DB on failure
            Config::set('database.connections.sqlite.database', database_path('database.sqlite'));
            DB::purge('sqlite');
            DB::reconnect('sqlite');
            
            $this->error("❌ Failed to create school: " . $e->getMessage());
        }
    }
}