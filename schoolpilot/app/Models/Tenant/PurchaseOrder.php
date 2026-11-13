<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrder extends Model
{
    protected $fillable = ['order_no', 'supplier_id', 'purchase_request_id', 'created_by', 'status', 'ordered_at', 'expected_at', 'received_at', 'notes'];
    protected $casts = ['ordered_at' => 'date', 'expected_at' => 'date', 'received_at' => 'date'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items() { return $this->hasMany(PurchaseOrderItem::class); }
}