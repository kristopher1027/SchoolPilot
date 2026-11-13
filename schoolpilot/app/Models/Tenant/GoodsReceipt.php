<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class GoodsReceipt extends Model
{
    protected $fillable = ['purchase_order_id', 'received_by', 'receipt_no', 'received_at', 'notes'];
    protected $casts = ['received_at' => 'date'];
    public function items() { return $this->hasMany(GoodsReceiptItem::class); }
}