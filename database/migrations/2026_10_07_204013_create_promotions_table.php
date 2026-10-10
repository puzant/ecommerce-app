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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();

            $table->string('type');

            $table->unsignedInteger('value');

            $table->unsignedInteger('minimum_cart_amount')->nullable();

            $table->unsignedInteger('maximum_discount')->nullable();

            $table->timestamp('starts_at')->nullable();

            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('usage_limit')->nullable();

            $table->unsignedInteger('per_customer_usage_limit')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
