<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class PurchaseRequestItem extends Model
{
    protected $fillable = ['purchase_request_id', 'item_id', 'quantity', 'estimated_unit_cost'];
    public function item() { return $this->belongsTo(InventoryItem::class, 'item_id'); }
}