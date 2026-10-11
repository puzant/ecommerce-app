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
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id', 'orders_user_id_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id', 'order_items_order_id_idx');
        });

        Schema::table('promotion_usages', function (Blueprint $table) {
            $table->index(
                ['promotion_id', 'user_id'],
                'promotion_usages_promotion_user_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_id_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_id_idx');
        });

        Schema::table('promotion_usages', function (Blueprint $table) {
            $table->dropIndex('promotion_usages_promotion_user_idx');
        });
    }
};
