<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\UploadedFile;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CategoryService
{
    public function getAdminPaginatedList(array $filters): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('services')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn(Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function getPublicPaginatedList(array $filters): LengthAwarePaginator
    {
        return Category::query()
            ->withCount([
                'services' => fn(Builder $query) => $query->where('status', Status::ACTIVE),
            ])
            ->where('status', Status::ACTIVE)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function create(array $data): Category
    {
        $image = $this->storeCategoryIcon($data['icon'] ?? null);

        return Category::query()->create([
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($data['slug'] ?? null, $data['name']),
            'icon' => $image['url'] ?? null,
            'icon_public_id' => $image['public_id'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? Status::ACTIVE,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $oldPublicId = $category->icon_public_id;
        $image = null;

        // Update name and automatically regenerate slug
        if (array_key_exists('name', $data)) {
            $category->name = $data['name'];

            $category->slug = $this->generateUniqueSlug(null, $data['name'], $category->id);
        }

        // Update description
        if (array_key_exists('description', $data)) {
            $category->description = $data['description'];
        }

        // Update status
        if (array_key_exists('status', $data)) {
            $category->status = $data['status'];
        }

        // Upload new category icon
        if (array_key_exists('icon', $data) && $data['icon'] instanceof UploadedFile) {
            $image = $this->storeCategoryIcon($data['icon']);

            if ($image) {
                $category->icon = $image['url'];
                $category->icon_public_id = $image['public_id'];
            }
        }

        // Save database first
        $category->save();

        // Delete old Cloudinary image after successful database update
        if ($image && $oldPublicId) {
            try {
                Cloudinary::uploadApi()->destroy($oldPublicId);
            } catch (\Throwable $e) {
                \Log::warning('Failed to delete old category icon.', [
                    'public_id' => $oldPublicId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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

        return $category
            ->services()
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

        while (Category::query()->when($ignoreId, fn(Builder $query) => $query->whereKeyNot($ignoreId))->where('slug', $resolvedSlug)->exists()) {
            $resolvedSlug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $resolvedSlug;
    }

    protected function storeCategoryIcon(mixed $icon): ?array
    {
        if (!$icon instanceof UploadedFile) {
            return null;
        }

        $result = Cloudinary::uploadApi()->upload($icon->getRealPath(), [
            'folder' => 'fixnow/categories',
        ]);

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }
}
