<?php

namespace App\Models;

use App\Enums\ProviderVerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderProfile extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'profile_image',
        'about',
        'experience_years',
        'verification_status',
        'average_rating',
        'total_reviews',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'average_rating' => 'decimal:2',
            'total_reviews' => 'integer',
            'verification_status' => ProviderVerificationStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerServices(): HasMany
    {
        return $this->hasMany(ProviderService::class);
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(ProviderServiceArea::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProviderAssignment::class);
    }

    public function availability(): HasOne
    {
        return $this->hasOne(ProviderAvailability::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
