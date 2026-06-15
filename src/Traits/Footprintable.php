<?php

namespace Tomeet\Laravel\Footprint\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait Footprintable
{
    /**
     * 该对象的所有足迹记录
     */
    public function footprints(): MorphMany
    {
        return $this->morphMany(config('footprint.footprint_model'), 'footprintable');
    }

    /**
     * 浏览过该对象的用户
     */
    public function footprinters(): BelongsToMany
    {
        return $this->belongsToMany(
            config('footprint.user_model'),
            config('footprint.footprint_table'),
            'footprintable_id',
            config('footprint.user_foreign_key')
        )
            ->where('footprintable_type', $this->getMorphClass())
            ->withPivot('viewed_at', 'title', 'image', 'meta')
            ->orderByPivot('viewed_at', 'desc');
    }

    /**
     * 该对象被浏览的去重用户数
     */
    public function uniqueFootprintersCount(): int
    {
        return $this->footprints()
            ->distinct(config('footprint.user_foreign_key'))
            ->count(config('footprint.user_foreign_key'));
    }

    /**
     * 该对象的足迹记录总数
     */
    public function totalFootprintsCount(): int
    {
        return $this->footprints()->count();
    }

    /**
     * 检查特定用户是否浏览过（支持预加载优化）
     */
    public function hasBeenFootprintedBy(Model $user): bool
    {
        $userModel = config('footprint.user_model');
        if (! $user instanceof $userModel) {
            return false;
        }

        return ($this->relationLoaded('footprints') ? $this->footprints : $this->footprints())
                ->where(config('footprint.user_foreign_key'), $user->getKey())
                ->count() > 0;
    }

    /**
     * 获取最近浏览的用户
     */
    public function recentFootprinters(int $limit = 10)
    {
        return $this->footprinters()->limit($limit)->get();
    }
}
