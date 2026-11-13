<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class InventoryCategory extends Model
{
    protected $fillable = ['name', 'category_type', 'description'];
    public function items() { return $this->hasMany(InventoryItem::class, 'category_id'); }
    public function assets() { return $this->hasMany(Asset::class, 'category_id'); }
}