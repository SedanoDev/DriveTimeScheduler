<?php

namespace App\Models\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSchool
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToSchool(): void
    {
        // Global Scope to filter by current school
        static::addGlobalScope('school', function (Builder $builder) {
            // Assuming tenant ID is resolved and stored in config or container
            // For now, we'll check if a tenant ID is set in the session or service container
            if (app()->has('current_school_id')) {
                $builder->where('school_id', app('current_school_id'));
            }
        });

        // Automatically set school_id when creating models
        static::creating(function ($model) {
            if (app()->has('current_school_id') && !$model->school_id) {
                $model->school_id = app('current_school_id');
            }
        });
    }

    /**
     * Get the school that owns the model.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
