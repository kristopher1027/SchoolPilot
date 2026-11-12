<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model
{
    protected $fillable = ['student_id', 'academic_session_id', 'term_id', 'total_amount', 'amount_paid', 'balance', 'status', 'due_date'];
    protected $casts = ['due_date' => 'date'];
    public function student() { return $this->belongsTo(Student::class); }
    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}