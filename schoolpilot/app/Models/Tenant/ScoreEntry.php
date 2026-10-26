<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class ScoreEntry extends Model
{
    protected $fillable = [
        'student_id', 'class_id', 'subject_id', 'academic_session_id', 
        'term_id', 'assessment_component_id', 'score', 'entered_by'
    ];
}