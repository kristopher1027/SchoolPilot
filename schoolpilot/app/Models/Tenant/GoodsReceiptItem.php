<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
class GoodsReceiptItem extends Model
{
    protected $fillable = ['goods_receipt_id', 'purchase_order_item_id', 'location_id', 'quantity_received'];
}