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
        Schema::create(config('payments.tables.orders'), function (Blueprint $table) {
            $table->id();
            $table->morphs('orderable');
            $table->string('order_id')->nullable();
            $table->string('stripe_id')->unique()->nullable();
            $table->string('stripe_status')->default('active');
            $table->string('stripe_price')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->double('price')->nullable();
            $table->string('status')->default('Waiting');
            $table->string('country')->default('Unknown');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('payments.tables.orders'));
    }
};
