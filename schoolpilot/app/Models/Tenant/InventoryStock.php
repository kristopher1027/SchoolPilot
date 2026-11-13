<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class InventoryStock extends Model
{
    protected $fillable = ['item_id', 'location_id', 'quantity'];
    protected $casts = ['quantity' => 'decimal:2'];
    public function item() { return $this->belongsTo(InventoryItem::class, 'item_id'); }
    public function location() { return $this->belongsTo(StockLocation::class, 'location_id'); }
}