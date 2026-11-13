<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class PurchaseRequest extends Model
{
    protected $fillable = ['request_no', 'requested_by', 'approved_by', 'status', 'reason', 'decision_note', 'approved_at'];
    protected $casts = ['approved_at' => 'datetime'];
    public function items() { return $this->hasMany(PurchaseRequestItem::class); }
}