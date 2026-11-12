<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class Payment extends Model
{
    protected $fillable = ['invoice_id', 'amount', 'method', 'reference', 'paid_at', 'recorded_by', 'receipt_no'];
    protected $casts = ['paid_at' => 'datetime'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function recorder() { return $this->belongsTo(Staff::class, 'recorded_by'); }
}