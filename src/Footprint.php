<?php

namespace Tomeet\Laravel\Footprint;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Footprint extends Model
{
    protected $fillable = [
        'user_id',
        'footprintable_type',
        'footprintable_id',
        'title',
        'image',
        'meta',
        'viewed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'viewed_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('footprint.footprint_table', 'footprints');
        parent::__construct($attributes);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($footprint) {
            $userForeignKey = config('footprint.user_foreign_key', 'user_id');
            $footprint->{$userForeignKey} = $footprint->{$userForeignKey} ?: auth()->id();

            if (config('footprint.uuids')) {
                $footprint->{$footprint->getKeyName()} = $footprint->{$footprint->getKeyName()} ?: (string) Str::orderedUuid();
            }
        });
    }

    public function footprintable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('footprint.user_model'), config('footprint.user_foreign_key'));
    }

    public function footprinter(): BelongsTo
    {
        return $this->user();
    }

    public function scopeWithType(Builder $query, string $type): Builder
    {
        return $query->where('footprintable_type', app($type)->getMorphClass());
    }
}
