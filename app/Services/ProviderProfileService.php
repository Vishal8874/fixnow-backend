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
        $profile = $this->getProviderProfile($provider);

        $profileImage = $profile->profile_image;
        $profileImagePublicId = $profile->profile_image_public_id;

        if (array_key_exists('profile_image', $data) && $data['profile_image'] instanceof UploadedFile) {
            // Upload new image first
            $image = $this->storeProfileImage($data['profile_image']);

            if ($image) {
                // Delete old image only after successful upload
                if ($profileImagePublicId) {
                    Cloudinary::destroy($profileImagePublicId);
                }

                $profileImage = $image['url'];
                $profileImagePublicId = $image['public_id'];
            }
        }

        $profile->fill([
            'profile_image' => $profileImage,
            'profile_image_public_id' => $profileImagePublicId,
            'about' => $data['about'] ?? $profile->about,
            'experience_years' => $data['experience_years'] ?? $profile->experience_years,
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

        $uploadedFile = $profileImage->storeOnCloudinary('fixnow/provider-profiles');

        return [
            'url' => $uploadedFile->getSecurePath(),
            'public_id' => $uploadedFile->getPublicId(),
        ];
    }
}
