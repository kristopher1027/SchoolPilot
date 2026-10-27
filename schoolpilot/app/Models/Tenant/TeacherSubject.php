<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class TeacherSubject extends Model
{
    protected $table = 'teacher_subjects';
    protected $fillable = ['staff_id', 'class_id', 'subject_id', 'academic_session_id', 'term_id'];

    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function staff() { return $this->belongsTo(Staff::class); } // Add this line
}