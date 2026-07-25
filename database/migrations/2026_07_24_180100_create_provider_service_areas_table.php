<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained('provider_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('postal_code', 20);
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();

            $table->unique(['provider_profile_id', 'postal_code']);
            $table->index('provider_profile_id');
            $table->index('postal_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_service_areas');
    }
};
