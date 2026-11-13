<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LibraryBook extends Model
{
    protected $fillable = [
        'category_id', 'title', 'author', 'isbn', 'publisher',
        'edition', 'publication_year', 'description',
    ];

    public function category()
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function copies()
    {
        return $this->hasMany(LibraryCopy::class, 'book_id');
    }

    public function reservations()
    {
        return $this->hasMany(LibraryReservation::class, 'book_id');
    }
}