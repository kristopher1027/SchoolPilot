<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'item_id', 'quantity_ordered', 'quantity_received', 'unit_cost'];
    public function item() { return $this->belongsTo(InventoryItem::class, 'item_id'); }
}