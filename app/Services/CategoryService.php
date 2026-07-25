<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CategoryService
{
    public function getAdminPaginatedList(array $filters): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('services')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function getPublicPaginatedList(array $filters): LengthAwarePaginator
    {
        return Category::query()
            ->withCount([
                'services' => fn (Builder $query) => $query->where('status', Status::ACTIVE),
            ])
            ->where('status', Status::ACTIVE)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function create(array $data): Category
    {
        return Category::query()->create([
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($data['slug'] ?? null, $data['name']),
            'icon' => $data['icon'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? Status::ACTIVE,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->fill([
            'name' => $data['name'] ?? $category->name,
            'slug' => array_key_exists('slug', $data)
                ? $this->generateUniqueSlug($data['slug'], $data['name'] ?? $category->name, $category->id)
                : $category->slug,
            'icon' => $data['icon'] ?? $category->icon,
            'description' => $data['description'] ?? $category->description,
            'status' => $data['status'] ?? $category->status,
        ])->save();

        return $category->fresh(['services']);
    }

    public function delete(Category $category): void
    {
        if ($category->services()->exists()) {
            throw new HttpException(409, 'Category cannot be deleted while services are linked to it.');
        }

        $category->delete();
    }

    public function getPublicCategoryServices(Category $category, array $filters): LengthAwarePaginator
    {
        if ($category->status !== Status::ACTIVE) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $category->services()
            ->with('category')
            ->where('status', Status::ACTIVE)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    protected function generateUniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name);
        $resolvedSlug = $baseSlug !== '' ? $baseSlug : Str::slug(Str::random(8));
        $counter = 1;

        while (
            Category::query()
                ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $resolvedSlug)
                ->exists()
        ) {
            $resolvedSlug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $resolvedSlug;
    }
}
