<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\TenantDatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed the standard sections, assessment components, grading scales,
        // skills rubrics, and tenant defaults for every newly provisioned school.
        $this->call(TenantDatabaseSeeder::class);

        DB::table('settings')->updateOrInsert(
            ['key' => 'school_name'],
            ['value' => '', 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
