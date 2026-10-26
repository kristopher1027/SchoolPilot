<?php

namespace App\Models\Platform;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'db_name',
        'status',
        'logo_url',
        'address',
        'phone',
        'email',
        'suspended_at',
    ];

    protected $casts = [
        'suspended_at' => 'datetime',
    ];
}
