<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class ProvisioningLog extends Model
{
    protected $connection = 'central_sqlite';

    protected $fillable = ['school_id', 'performed_by', 'action', 'status', 'notes'];
}
