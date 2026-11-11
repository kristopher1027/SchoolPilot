<?php

namespace App\Models\Tenant;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /**
     * Tenant users must always be loaded from the currently selected school database.
     */
    protected $connection = 'tenant_sqlite';

    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'is_active', 'photo_url'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];
}