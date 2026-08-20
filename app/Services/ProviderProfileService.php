<?php

namespace App\Services;

use App\Enums\ProviderVerificationStatus;
use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProviderProfileService
{
    public function view(User $provider): ProviderProfile
    {
        return $this->getProviderProfile($provider);
    }

    public function create(User $provider, array $data): ProviderProfile
    {
        if ($provider->providerProfile()->exists()) {
            throw new HttpException(409, 'Provider profile already exists.');
        }

        return DB::transaction(function () use ($provider, $data): ProviderProfile {
            $image = $this->storeProfileImage($data['profile_image'] ?? null);

            return $provider
                ->providerProfile()
                ->create([
                    'profile_image' => $image['url'] ?? null,
                    'profile_image_public_id' => $image['public_id'] ?? null,
                    'about' => $data['about'],
                    'experience_years' => $data['experience_years'],
                    'verification_status' => ProviderVerificationStatus::PENDING,
                    'average_rating' => 0,
                    'total_reviews' => 0,
                ])
                ->load('user');
        });
    }

    public function update(User $provider, array $data): ProviderProfile
    {
        \Log::info('PROFILE UPDATE DATA', [
            'about_exists' => array_key_exists('about', $data),
            'about' => $data['about'] ?? null,
            'experience_exists' => array_key_exists('experience_years', $data),
            'experience_years' => $data['experience_years'] ?? null,
            'image_exists' => array_key_exists('profile_image', $data),
            'image_type' => isset($data['profile_image']) ? get_class($data['profile_image']) : null,
        ]);

        $profile = $this->getProviderProfile($provider);

        // Update text fields directly
        if (array_key_exists('about', $data)) {
            $profile->about = $data['about'];
        }

        if (array_key_exists('experience_years', $data)) {
            $profile->experience_years = $data['experience_years'];
        }

        // Handle image
        if (array_key_exists('profile_image', $data) && $data['profile_image'] instanceof UploadedFile) {
            $oldPublicId = $profile->profile_image_public_id;

            // Upload new image first
            $image = $this->storeProfileImage($data['profile_image']);

            if ($image) {
                $profile->profile_image = $image['url'];
                $profile->profile_image_public_id = $image['public_id'];

                // Save database first
                $profile->save();

                // Delete old image only after successful save
                if ($oldPublicId) {
                    try {
                        Cloudinary::uploadApi()->destroy($oldPublicId);
                    } catch (\Throwable $e) {
                        \Log::warning('Failed to delete old Cloudinary image.', [
                            'public_id' => $oldPublicId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return $profile->fresh(['user']);
            }
        }
        \Log::info('PROFILE BEFORE SAVE', [
            'id' => $profile->id,
            'about' => $profile->about,
            'experience_years' => $profile->experience_years,
            'profile_image' => $profile->profile_image,
            'profile_image_public_id' => $profile->profile_image_public_id,
            'dirty' => $profile->getDirty(),
        ]);

        $profile->save();

        return $profile->fresh(['user']);
    }
    public function listPending(array $filters): LengthAwarePaginator
    {
        return ProviderProfile::query()
            ->with('user')
            ->where('verification_status', ProviderVerificationStatus::PENDING)
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function listAll(array $filters): LengthAwarePaginator
    {
        return ProviderProfile::query()
            ->with('user')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();
    }

    public function viewProviderForAdmin(User $provider): ProviderProfile
    {
        return $this->getProviderProfile($provider);
    }

    public function approve(User $provider): ProviderProfile
    {
        $profile = $this->getProviderProfile($provider);

        if ($profile->verification_status === ProviderVerificationStatus::APPROVED) {
            throw new HttpException(409, 'Provider is already approved.');
        }

        $profile
            ->forceFill([
                'verification_status' => ProviderVerificationStatus::APPROVED,
            ])
            ->save();

        return $profile->fresh(['user']);
    }

    public function reject(User $provider): ProviderProfile
    {
        $profile = $this->getProviderProfile($provider);

        if ($profile->verification_status === ProviderVerificationStatus::REJECTED) {
            throw new HttpException(409, 'Provider is already rejected.');
        }

        $profile
            ->forceFill([
                'verification_status' => ProviderVerificationStatus::REJECTED,
            ])
            ->save();

        return $profile->fresh(['user']);
    }

    protected function getProviderProfile(User $provider): ProviderProfile
    {
        if ($provider->role !== UserRole::PROVIDER) {
            throw new HttpException(404, 'Resource not found.');
        }

        $profile = $provider->providerProfile()->with('user')->first();

        if (!$profile) {
            throw new HttpException(404, 'Resource not found.');
        }

        return $profile;
    }

    protected function storeProfileImage(mixed $profileImage): ?array
    {
        if (!$profileImage instanceof UploadedFile) {
            return null;
        }

        $result = Cloudinary::uploadApi()->upload($profileImage->getRealPath(), [
            'folder' => 'fixnow/provider-profiles',
        ]);

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }
}
