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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->unique()
                ->constrained('bookings')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Razorpay identifiers
            $table->string('razorpay_order_id')
                ->unique();

            $table->string('razorpay_payment_id')
                ->nullable()
                ->unique();

            // Payment details
            $table->string('payment_method')->default('razorpay')->index();

            $table->string('payment_status')
                ->default('pending')
                ->index();

            // Store amount in rupees
            $table->decimal('amount', 10, 2);

            $table->string('currency', 3)->default('INR');

            $table->timestamp('paid_at')->nullable();

            // Gateway information
            $table->string('gateway')->default('razorpay');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};