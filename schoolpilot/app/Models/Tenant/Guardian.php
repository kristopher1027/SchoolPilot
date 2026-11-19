<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    /** Tenant records must always resolve against the active school's database. */
    protected $connection = 'tenant_sqlite';

    protected $table = 'parents';

    protected $fillable = ['user_id', 'occupation', 'address', 'relationship'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_parents', 'parent_id', 'student_id')
            ->withPivot('is_primary', 'relationship', 'is_emergency_contact', 'can_pickup')
            ->withTimestamps();
    }
}
