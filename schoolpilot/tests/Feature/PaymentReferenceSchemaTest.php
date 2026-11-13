<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PaymentReferenceSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant_sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('tenant_sqlite');
        DB::setDefaultConnection('tenant_sqlite');
        Schema::connection('tenant_sqlite')->create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        DB::purge('tenant_sqlite');
        parent::tearDown();
    }

    public function test_payment_reference_migration_enforces_unique_non_null_references(): void
    {
        $migration = require database_path('migrations/tenant/2026_11_13_000007_add_unique_reference_to_payments_table.php');
        $migration->up();

        DB::table('payments')->insert(['reference' => 'BANK-REF-001']);
        DB::table('payments')->insert(['reference' => null]);
        DB::table('payments')->insert(['reference' => null]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('payments')->insert(['reference' => 'BANK-REF-001']);
    }

    public function test_payment_reference_migration_refuses_existing_duplicate_references(): void
    {
        DB::table('payments')->insert([
            ['reference' => 'DUPLICATE-001'],
            ['reference' => 'DUPLICATE-001'],
        ]);

        $migration = require database_path('migrations/tenant/2026_11_13_000007_add_unique_reference_to_payments_table.php');

        $this->expectException(RuntimeException::class);
        $migration->up();
    }
}
