<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CustomerProfileService
{
    public function getProfile(User $user): CustomerProfile
    {
        return $user->customerProfile()->firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    public function update(User $user, array $data): CustomerProfile
    {
        $profile = $this->getProfile($user);

        if (array_key_exists('date_of_birth', $data)) {
            $profile->date_of_birth = $data['date_of_birth'];
        }

        if (array_key_exists('gender', $data)) {
            $profile->gender = $data['gender'];
        }

        $oldPublicId = $profile->customer_image_public_id;
        $newImage = null;

        if (
            array_key_exists('customer_image', $data) &&
            $data['customer_image'] instanceof UploadedFile
        ) {
            $newImage = $this->storeCustomerImage($data['customer_image']);

            if ($newImage) {
                $profile->customer_image = $newImage['url'];
                $profile->customer_image_public_id = $newImage['public_id'];
            }
        }

        $profile->save();

        // Delete old Cloudinary image only after successful DB save
        if ($newImage && $oldPublicId) {
            try {
                Cloudinary::uploadApi()->destroy($oldPublicId);
            } catch (\Throwable $e) {
                \Log::warning('Failed to delete old customer image.', [
                    'public_id' => $oldPublicId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $profile->fresh(['user']);
    }

    protected function storeCustomerImage(UploadedFile $image): ?array
    {
        $result = Cloudinary::uploadApi()->upload(
            $image->getRealPath(),
            [
                'folder' => 'fixnow/customers',
            ]
        );

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }
}