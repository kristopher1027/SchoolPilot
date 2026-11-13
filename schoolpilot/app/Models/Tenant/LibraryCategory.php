<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class LibraryCategory extends Model
{
    protected $fillable = ['name', 'description'];

    public function books()
    {
        return $this->hasMany(LibraryBook::class, 'category_id');
    }
}