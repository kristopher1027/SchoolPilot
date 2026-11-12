<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class FeeItem extends Model
{
    protected $fillable = ['name', 'section_id', 'class_id', 'academic_session_id', 'term_id', 'amount'];
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function section() { return $this->belongsTo(Section::class); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); }
}