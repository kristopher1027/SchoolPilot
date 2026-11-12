<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AdmissionApplication extends Model
{
    protected $fillable = [
        'application_no', 'first_name', 'last_name', 'middle_name', 'gender',
        'date_of_birth', 'section_id', 'class_id', 'guardian_name', 'guardian_email',
        'guardian_phone', 'address', 'previous_school', 'status', 'review_notes',
        'reviewed_by', 'reviewed_at', 'student_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
