<?php

declare(strict_types=1);

use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    */

    'tenant_model' => \App\Models\Platform\Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | Unique ID Generator
    |--------------------------------------------------------------------------
    */

    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Domain Model
    |--------------------------------------------------------------------------
    */

    'domain_model' => Domain::class,

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    */

    'central_domains' => [
        '127.0.0.1',
        'localhost',
        'schoolpilot.test',
    ],

    /*
    |--------------------------------------------------------------------------
    | Identification (Path-based for local testing)
    |--------------------------------------------------------------------------
    */

    'identification' => [
        'path' => [
            'middleware' => [
                InitializeTenancyByPath::class,
                PreventAccessFromCentralDomains::class,
            ],
            'prefix' => 'api/school', // The URL will be /api/school/greenfield/...
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bootstrappers
    |--------------------------------------------------------------------------
    */

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'central'),

        'template_tenant_connection' => 'tenant',

        'prefix' => env('TENANCY_DATABASE_PREFIX', 'schoolpilot_tenant_'),

        'managers' => [
            'sqlite' => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
        
        'sqlite' => [
            'path' => database_path('tenants'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'tag_base' => 'tenant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem
    |--------------------------------------------------------------------------
    */

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    */

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
        // Stancl\Tenancy\Features\UniversalRoutes::class,
        // Stancl\Tenancy\Features\TenantConfig::class,
        // Stancl\Tenancy\Features\CrossDomainRedirect::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    'routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Migration Parameters
    |--------------------------------------------------------------------------
    */

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder Parameters
    |--------------------------------------------------------------------------
    */

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\Tenant\\DatabaseSeeder',
        '--force' => true,
    ],

];