<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $table = 'attendance_records';

    protected $fillable = [
        'student_id', 'class_id', 'date', 'status', 'check_in_time',
        'check_out_time', 'picked_up_by', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function recorder()
    {
        return $this->belongsTo(Staff::class, 'recorded_by');
    }
}
