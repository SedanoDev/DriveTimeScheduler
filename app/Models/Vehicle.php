<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, BelongsToSchool;

    protected $fillable = [
        'plate',
        'model',
        'type', // manual/automatic
        'status', // active/maintenance
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
