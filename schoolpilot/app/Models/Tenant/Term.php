<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $fillable = ['academic_session_id', 'name', 'start_date', 'end_date', 'is_current'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
