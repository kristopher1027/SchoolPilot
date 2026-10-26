<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'is_current'];

    public function terms()
    {
        return $this->hasMany(Term::class);
    }
}