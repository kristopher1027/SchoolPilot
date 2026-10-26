<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'admission_no',
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'date_of_birth',
        'section_id',
        'class_id',
        'date_admitted',
        'status',
        'address',
        'blood_group',
        'genotype',
        'allergies',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_admitted' => 'date',
    ];

    // Relationships
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}