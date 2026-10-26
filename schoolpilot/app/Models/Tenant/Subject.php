<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['name', 'code', 'section_id', 'department_id'];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}