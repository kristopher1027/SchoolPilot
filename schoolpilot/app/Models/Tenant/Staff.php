<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = [
        'user_id', 'staff_no', 'designation', 'date_hired', 'qualification', 'address'
    ];

    protected $casts = [
        'date_hired' => 'date',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'staff_departments')->withPivot('is_hod')->withTimestamps();
    }
}