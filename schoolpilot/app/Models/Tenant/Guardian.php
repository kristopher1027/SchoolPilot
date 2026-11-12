<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    protected $table = 'parents';

    protected $fillable = ['user_id', 'occupation', 'address', 'relationship'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_parents', 'parent_id', 'student_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
