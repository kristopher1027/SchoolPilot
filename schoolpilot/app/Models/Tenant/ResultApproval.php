<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ResultApproval extends Model
{
    protected $fillable = [
        'student_id', 'class_id', 'term_id', 'academic_session_id', 'status',
        'submitted_by', 'class_teacher_approved_by', 'hod_approved_by', 'dean_approved_by', 'published_by', 'rejection_reason'
    ];

    public function student() { return $this->belongsTo(Student::class); }
    public function class() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
}