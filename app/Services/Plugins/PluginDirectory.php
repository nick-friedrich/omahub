<?php

namespace App\Services\Plugins;

use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Plugin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PluginDirectory
{
    public const PER_PAGE = 12;

    public function totalPublishedCount(): int
    {
        return Plugin::query()->published()->count();
    }

    /** @return Builder<Plugin> */
    public function baseQuery(): Builder
    {
        return Plugin::query()
            ->published()
            ->with(['categories', 'tags']);
    }

    /** @return Collection<int, Plugin> */
    public function recentlyUpdated(int $limit = 6): Collection
    {
        return $this->baseQuery()->orderByDesc('last_pushed_at')->limit($limit)->get();
    }

    /** @return Collection<int, Plugin> */
    public function newest(int $limit = 6): Collection
    {
        return $this->baseQuery()->orderByDesc('published_at')->limit($limit)->get();
    }

    /** @return Collection<int, Plugin> */
    public function popular(int $limit = 6): Collection
    {
        return $this->baseQuery()->orderByDesc('stars_count')->limit($limit)->get();
    }

    public function browse(?string $categorySlug = null, ?string $tagSlug = null): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($categorySlug !== null && $categorySlug !== '') {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $categorySlug));
        }

        if ($tagSlug !== null && $tagSlug !== '') {
            $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $tagSlug));
        }

        return $query->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();
    }

    public function search(string $term): LengthAwarePaginator
    {
        $term = Str::of($term)->trim()->toString();
        $query = $this->baseQuery();

        if ($term !== '') {
            $needle = "%{$term}%";
            $query->where(function (Builder $q) use ($needle) {
                $q->whereLike('name', $needle)->orWhereLike('description', $needle);
            });
        }

        return $query->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();
    }

    public function findBySlug(string $slug): ?Plugin
    {
        return $this->baseQuery()->where('slug', $slug)->first();
    }

    /** @return Collection<int, Category> */
    public function categoriesWithCounts(): Collection
    {
        return Category::query()
            // Larastan cannot resolve model scopes on the untyped builder of a
            // relation-callback, so the published() scope conditions are inlined.
            ->withCount(['plugins' => fn (Builder $q): Builder => $q
                ->where('status', PluginStatus::Published)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()),
            ])
            ->orderBy('name')
            ->get();
    }
}
