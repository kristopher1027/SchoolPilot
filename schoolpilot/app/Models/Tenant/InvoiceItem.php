<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'fee_item_id', 'amount'];
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function feeItem() { return $this->belongsTo(FeeItem::class); }
}