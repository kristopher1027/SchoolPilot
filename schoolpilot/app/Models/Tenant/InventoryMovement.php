<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class InventoryMovement extends Model
{
    protected $fillable = ['item_id', 'location_id', 'movement_type', 'quantity', 'reference', 'notes', 'recorded_by', 'movement_at'];
    protected $casts = ['quantity' => 'decimal:2', 'movement_at' => 'datetime'];
    public function item() { return $this->belongsTo(InventoryItem::class, 'item_id'); }
    public function location() { return $this->belongsTo(StockLocation::class, 'location_id'); }
}