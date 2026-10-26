<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $table = 'classes';
    protected $fillable = ['section_id', 'name', 'level', 'next_class_id'];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }
}