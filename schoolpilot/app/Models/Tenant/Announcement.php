<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'author_id', 'title', 'body', 'audience', 'status', 'published_at', 'expires_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeVisibleTo($query, User $user)
    {
        $audience = match ($user->role) {
            'parent' => ['all', 'parents'],
            'student' => ['all', 'students'],
            'class_teacher', 'subject_teacher' => ['all', 'staff', 'teachers'],
            'bursar' => ['all', 'staff'],
            default => ['all', 'staff', 'teachers', 'parents', 'students'],
        };

        return $query->whereIn('audience', $audience)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }
}
