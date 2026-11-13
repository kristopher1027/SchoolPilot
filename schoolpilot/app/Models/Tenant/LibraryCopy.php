<?php

namespace App\\Models\\Tenant;

use Illuminate\\Database\\Eloquent\\Model;

class LibraryCopy extends Model
{
    protected $fillable = ['book_id', 'accession_no', 'condition', 'status'];

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }

    public function loans()
    {
        return $this->hasMany(LibraryLoan::class, 'copy_id');
    }

    public function activeLoan()
    {
        return $this->hasOne(LibraryLoan::class, 'copy_id')->whereNull('returned_at');
    }
}