<?php

namespace Tomeet\Laravel\Footprint\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Tomeet\Laravel\Footprint\Footprint;

trait Footprinter
{
    /**
     * 用户的所有足迹记录，按浏览时间倒序
     */
    public function footprints(): HasMany
    {
        return $this->hasMany(
            config('footprint.footprint_model'),
            config('footprint.user_foreign_key'),
            $this->getKeyName()
        );
    }

    /**
     * 记录浏览足迹
     */
    public function recordFootprint(Model $object, array $snapshot = []): Footprint
    {
        $footprint = $this->footprints()->updateOrCreate(
            [
                'footprintable_type' => $object->getMorphClass(),
                'footprintable_id' => $object->getKey(),
            ],
            [
                'title' => $snapshot['title'] ?? $object->title ?? $object->name ?? null,
                'image' => $snapshot['image'] ?? $object->image ?? $object->thumb ?? null,
                'meta' => $snapshot['meta'] ?? $this->extractFootprintMeta($object),
                'viewed_at' => now(),
            ]
        );

        return $footprint;
    }

    /**
     * 获取足迹中的特定类型对象
     */
    public function getFootprintItems(string $model): Builder
    {
        return app($model)->whereHas('footprinters', function ($query) {
            $query->where(config('footprint.user_foreign_key'), $this->getKey());
        });
    }

    /**
     * 获取最近浏览的足迹
     */
    public function recentFootprints(int $limit = 20): Collection
    {
        return $this->footprints()
            ->with('footprintable')
            ->limit($limit)
            ->get();
    }

    /**
     * 分页获取足迹
     */
    public function footprintPaginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->footprints()
            ->with('footprintable')
            ->paginate($perPage);
    }

    /**
     * 检查用户是否浏览过某对象（支持预加载优化）
     */
    public function hasFootprinted(Model $object): bool
    {
        return ($this->relationLoaded('footprints') ? $this->footprints : $this->footprints())
                ->where('footprintable_id', $object->getKey())
                ->where('footprintable_type', $object->getMorphClass())
                ->count() > 0;
    }

    /**
     * 获取某对象的浏览记录（支持预加载优化）
     */
    public function getFootprintOf(Model $object): ?Footprint
    {
        return ($this->relationLoaded('footprints') ? $this->footprints : $this->footprints())
            ->where('footprintable_id', $object->getKey())
            ->where('footprintable_type', $object->getMorphClass())
            ->first();
    }

    /**
     * 清空所有足迹
     */
    public function clearFootprints(): void
    {
        $this->footprints()->delete();
    }

    /**
     * 删除特定类型的足迹
     */
    public function clearFootprintsByType(string $model): void
    {
        $this->footprints()
            ->where('footprintable_type', $model)
            ->delete();
    }

    /**
     * 批量附加浏览状态到集合（支持预加载优化）
     */
    public function attachFootprintStatus($footprintables)
    {
        if ($footprintables instanceof Model) {
            $footprintables->setAttribute('has_footprinted', $this->hasFootprinted($footprintables));
            return $footprintables;
        }

        if (empty($footprintables) || !($first = collect($footprintables)->first())) {
            return $footprintables;
        }

        $footprintableType = get_class($first);

        // ⭐ 预加载优化
        if ($this->relationLoaded('footprints')) {
            $footprintedIds = $this->footprints
                ->where('footprintable_type', $footprintableType)
                ->pluck('footprintable_id');
        } else {
            $footprintableIds = collect($footprintables)->pluck('id');
            $footprintedIds = $this->footprints()
                ->where('footprintable_type', $footprintableType)
                ->whereIn('footprintable_id', $footprintableIds)
                ->pluck('footprintable_id');
        }

        collect($footprintables)->each(function ($item) use ($footprintedIds) {
            $item->setAttribute('has_footprinted', $footprintedIds->contains($item->getKey()));
        });

        return $footprintables;
    }

    /**
     * 提取对象的元数据
     */
    protected function extractFootprintMeta(Model $object): ?array
    {
        if (method_exists($object, 'getFootprintMeta')) {
            return $object->getFootprintMeta();
        }

        return null;
    }
}
