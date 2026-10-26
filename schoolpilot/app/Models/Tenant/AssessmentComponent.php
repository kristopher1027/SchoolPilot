<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AssessmentComponent extends Model
{
    protected $fillable = [
        'section_id', 'subject_id', 'name', 'type', 
        'max_score', 'weight', 'display_order', 'is_active'
    ];
}