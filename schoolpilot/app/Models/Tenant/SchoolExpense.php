<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class SchoolExpense extends Model
{
    protected $fillable = [
        'category_id', 'description', 'vendor', 'amount', 'payment_method',
        'reference', 'expense_date', 'status', 'paid_at', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'paid_at' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
