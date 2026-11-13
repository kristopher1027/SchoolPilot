<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LibraryLoan extends Model
{
    protected $fillable = [
        'copy_id', 'member_type', 'member_id', 'issued_by', 'returned_to',
        'issued_at', 'due_at', 'returned_at', 'fine_amount', 'fine_paid',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
        'returned_at' => 'date',
        'fine_amount' => 'decimal:2',
        'fine_paid' => 'decimal:2',
    ];

    public function copy()
    {
        return $this->belongsTo(LibraryCopy::class, 'copy_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->returned_at === null && $this->due_at->lt(today());
    }
}