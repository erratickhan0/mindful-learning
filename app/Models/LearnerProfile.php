<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'grade_level',
        'reading_level',
        'pace_level',
        'confidence_level',
        'attention_window_minutes',
        'preferred_language',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
