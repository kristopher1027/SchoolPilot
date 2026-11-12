<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class TimetableEntry extends Model
{
    protected $fillable = [
        'class_id', 'subject_id', 'staff_id', 'academic_session_id',
        'term_id', 'day_of_week', 'start_time', 'end_time', 'room',
    ];

    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function term() { return $this->belongsTo(Term::class); }
}
