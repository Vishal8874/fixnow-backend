<?php

namespace App\Services;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceService
{
    public function getAdminPaginatedList(array $filters): LengthAwarePaginator
    {
        return Service::query()
            ->with('category')
            ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function getPublicPaginatedList(array $filters, mixed $user = null): LengthAwarePaginator
    {
        return Service::query()
            ->with('category')
            ->when(
                ! $user || $user->role !== UserRole::ADMIN || ! ($filters['status'] ?? null),
                fn (Builder $query) => $query->where('status', Status::ACTIVE)
            )
            ->when(
                $user && $user->role === UserRole::ADMIN && ($filters['status'] ?? null),
                fn (Builder $query) => $query->where('status', $filters['status'])
            )
            ->whereHas('category', function (Builder $query): void {
                $query->where('status', Status::ACTIVE);
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function create(array $data): Service
    {
        $this->ensureCategoryAllowsServiceName($data['category_id'], $data['name']);

        return Service::query()->create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($data['slug'] ?? null, $data['name']),
            'image' => $data['image'] ?? null,
            'description' => $data['description'] ?? null,
            'estimated_duration' => $data['estimated_duration'],
            'base_price' => $data['base_price'],
            'status' => $data['status'] ?? Status::ACTIVE,
        ])->load('category');
    }

    public function update(Service $service, array $data): Service
    {
        $categoryId = $data['category_id'] ?? $service->category_id;
        $name = $data['name'] ?? $service->name;
        $this->ensureCategoryAllowsServiceName($categoryId, $name, $service->id);

        $service->fill([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => array_key_exists('slug', $data)
                ? $this->generateUniqueSlug($data['slug'], $data['name'] ?? $service->name, $service->id)
                : $service->slug,
            'image' => $data['image'] ?? $service->image,
            'description' => $data['description'] ?? $service->description,
            'estimated_duration' => $data['estimated_duration'] ?? $service->estimated_duration,
            'base_price' => $data['base_price'] ?? $service->base_price,
            'status' => $data['status'] ?? $service->status,
        ])->save();

        return $service->fresh(['category']);
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }

    protected function generateUniqueSlug(?string $slug, string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($slug ?: $name);
        $resolvedSlug = $baseSlug !== '' ? $baseSlug : Str::slug(Str::random(8));
        $counter = 1;

        while (
            Service::query()
                ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->where('slug', $resolvedSlug)
                ->exists()
        ) {
            $resolvedSlug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $resolvedSlug;
    }

    protected function ensureCategoryAllowsServiceName(int $categoryId, string $name, ?int $ignoreId = null): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $duplicateExists = $category->services()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->exists();

        if ($duplicateExists) {
            throw new HttpException(422, 'A service with this name already exists in the selected category.');
        }
    }
}
