<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class Asset extends Model
{
    protected $fillable = ['category_id', 'supplier_id', 'location_id', 'assigned_to', 'created_by', 'asset_tag', 'name', 'serial_no', 'description', 'purchase_date', 'purchase_cost', 'warranty_until', 'condition', 'status'];
    protected $casts = ['purchase_date' => 'date', 'warranty_until' => 'date', 'purchase_cost' => 'decimal:2'];
    public function category() { return $this->belongsTo(InventoryCategory::class, 'category_id'); }
    public function location() { return $this->belongsTo(StockLocation::class, 'location_id'); }
    public function maintenance() { return $this->hasMany(AssetMaintenance::class); }
}