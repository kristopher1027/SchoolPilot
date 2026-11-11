<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\TenantDatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Safe to run for new and existing tenants: defaults are only inserted
        // when missing, so school-specific settings and existing records survive.
        $this->call(TenantDatabaseSeeder::class);

        DB::table('settings')->insertOrIgnore([
            'key' => 'school_name',
            'value' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
