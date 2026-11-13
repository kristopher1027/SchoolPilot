<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class Supplier extends Model
{
    protected $fillable = ['name', 'contact_person', 'email', 'phone', 'address', 'notes', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }
}