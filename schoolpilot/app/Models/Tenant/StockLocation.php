<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class StockLocation extends Model
{
    protected $fillable = ['name', 'description'];
    public function stocks() { return $this->hasMany(InventoryStock::class, 'location_id'); }
}