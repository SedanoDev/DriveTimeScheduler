<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'branding_config',
        'timezone',
        'locale',
    ];

    protected $casts = [
        'branding_config' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
