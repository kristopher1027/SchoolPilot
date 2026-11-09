<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'school_name'],
            ['value' => '', 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
