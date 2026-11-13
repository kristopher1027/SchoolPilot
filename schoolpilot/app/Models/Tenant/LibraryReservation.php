<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LibraryReservation extends Model
{
    protected $fillable = [
        'book_id', 'member_type', 'member_id', 'status', 'reserved_at', 'fulfilled_at',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }
}