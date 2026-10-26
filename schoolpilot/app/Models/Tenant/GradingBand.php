<?php
namespace App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class GradingBand extends Model
{
    protected $fillable = ['grading_scale_id', 'min_score', 'max_score', 'grade', 'remark', 'descriptor', 'points'];
    
    public function gradingScale()
    {
        return $this->belongsTo(GradingScale::class);
    }
}