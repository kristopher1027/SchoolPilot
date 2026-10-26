<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $fillable = ['academic_session_id', 'name', 'start_date', 'end_date', 'is_current'];
}