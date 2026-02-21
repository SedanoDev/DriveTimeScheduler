<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstructorAvailability extends Model
{
    use HasFactory, BelongsToSchool;

    protected $fillable = [
        'instructor_id',
        'day_of_week', // 0=Sunday
        'start_time',
        'end_time',
    ];

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
