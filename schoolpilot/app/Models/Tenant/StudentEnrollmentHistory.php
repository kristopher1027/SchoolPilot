<?php

namespace App\\Models\\Tenant;

use Illuminate\\Database\\Eloquent\\Model;

class StudentEnrollmentHistory extends Model
{
    protected $table = 'student_enrollment_history';

    protected $fillable = [
        'student_id',
        'from_section_id',
        'to_section_id',
        'from_class_id',
        'to_class_id',
        'from_status',
        'to_status',
        'change_type',
        'reason',
        'effective_on',
        'changed_by',
    ];

    protected $casts = [
        'effective_on' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function fromSection()
    {
        return $this->belongsTo(Section::class, 'from_section_id');
    }

    public function toSection()
    {
        return $this->belongsTo(Section::class, 'to_section_id');
    }

    public function fromClass()
    {
        return $this->belongsTo(SchoolClass::class, 'from_class_id');
    }

    public function toClass()
    {
        return $this->belongsTo(SchoolClass::class, 'to_class_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
