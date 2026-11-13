<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class AssetMaintenance extends Model
{
    protected $table = 'asset_maintenance';
    protected $fillable = ['asset_id', 'reported_by', 'status', 'description', 'cost', 'service_date', 'next_service_date', 'notes'];
    protected $casts = ['cost' => 'decimal:2', 'service_date' => 'date', 'next_service_date' => 'date'];
    public function asset() { return $this->belongsTo(Asset::class); }
}