<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class InventoryItem extends Model
{
    protected $fillable = ['category_id', 'sku', 'name', 'description', 'unit', 'reorder_level', 'is_active'];
    protected $casts = ['reorder_level' => 'decimal:2', 'is_active' => 'boolean'];
    public function category() { return $this->belongsTo(InventoryCategory::class, 'category_id'); }
    public function stocks() { return $this->hasMany(InventoryStock::class, 'item_id'); }
    public function movements() { return $this->hasMany(InventoryMovement::class, 'item_id'); }
}