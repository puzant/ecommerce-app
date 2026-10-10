<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_checkout(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 1000,
            'stock_quantity' => 10,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertStatus(201)
            ->assertJsonPath('order.subtotal', 2000)
            ->assertJsonPath('order.discount', 0)
            ->assertJsonPath('order.total', 2000);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'subtotal' => 2000,
            'discount' => 0,
            'total' => 2000,
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price' => 1000,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);

        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    public function test_customer_cannot_checkout_when_stock_is_insufficient(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 1000,
            'stock_quantity' => 1,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout');

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stock']);

        // Stock must remain unchanged.
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);

        // No order should have been created.
        $this->assertDatabaseCount('orders', 0);

        // Cart should still contain the item.
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_percentage_promotion_is_applied(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 10,
        ]);

        $promotion = \App\Models\Promotion::factory()->create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20,
            'minimum_cart_amount' => 0,
            'maximum_discount' => null,
            'usage_limit' => 100,
            'per_customer_usage_limit' => 1,
            'active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/checkout', [
            'promotion_code' => $promotion->code,
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('order.subtotal', 10000)
            ->assertJsonPath('order.discount', 2000)
            ->assertJsonPath('order.total', 8000);

        $this->assertDatabaseHas('promotion_usages', [
            'promotion_id' => $promotion->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_customer_cannot_reuse_promotion_beyond_per_customer_limit(): void
    {
        $user = User::factory()->create();

        $promotion = \App\Models\Promotion::factory()->create([
            'code' => 'ONCE',
            'type' => 'percentage',
            'value' => 20,
            'minimum_cart_amount' => 0,
            'maximum_discount' => null,
            'usage_limit' => 100,
            'per_customer_usage_limit' => 1,
            'active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 10,
        ]);

        // First order
        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'promotion_code' => $promotion->code,
        ])->assertStatus(201);

        // Put another item in the cart.
        $cart = Cart::where('user_id', $user->id)->first();

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Second attempt using the same promotion.
        $response = $this->postJson('/api/checkout', [
            'promotion_code' => $promotion->code,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        // Only the first order should exist.
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_cancelling_order_restores_stock(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 1000,
            'stock_quantity' => 10,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        // Create the order: stock should go from 10 to 8.
        $checkoutResponse = $this->postJson('/api/checkout');

        $checkoutResponse->assertStatus(201);

        $orderId = $checkoutResponse->json('order.id');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);

        // Cancel the order: stock should return to 10.
        $this->patchJson("/api/orders/{$orderId}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_cancelling_order_twice_does_not_restore_stock_twice(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 1000,
            'stock_quantity' => 10,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($user);

        $checkoutResponse = $this->postJson('/api/checkout');
        $checkoutResponse->assertStatus(201);

        $orderId = $checkoutResponse->json('order.id');

        // First cancellation restores the two units.
        $this->patchJson("/api/orders/{$orderId}/cancel")
            ->assertOk();

        // Second cancellation must be rejected.
        $this->patchJson("/api/orders/{$orderId}/cancel")
            ->assertStatus(422);

        // Stock must remain 10, not increase to 12.
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 1000,
            'stock_quantity' => 10,
        ]);

        $cart = Cart::create([
            'user_id' => $customerA->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Customer A creates the order.
        Sanctum::actingAs($customerA);

        $checkoutResponse = $this->postJson('/api/checkout');
        $checkoutResponse->assertStatus(201);

        $orderId = $checkoutResponse->json('order.id');

        // Customer B tries to cancel Customer A's order.
        Sanctum::actingAs($customerB);

        $this->patchJson("/api/orders/{$orderId}/cancel")
            ->assertStatus(403);

        // The order must remain pending.
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'pending',
        ]);

        // The stock must not be restored.
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
    }

    public function test_fixed_promotion_is_applied(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 10,
        ]);

        $promotion = \App\Models\Promotion::factory()->create([
            'code' => 'TAKE5',
            'type' => 'fixed',
            'value' => 500,
            'minimum_cart_amount' => 0,
            'maximum_discount' => null,
            'active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'promotion_code' => $promotion->code,
        ])
            ->assertStatus(201)
            ->assertJsonPath('order.subtotal', 10000)
            ->assertJsonPath('order.discount', 500)
            ->assertJsonPath('order.total', 9500);
    }

    public function test_expired_promotion_is_rejected(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 10,
        ]);

        $promotion = \App\Models\Promotion::factory()->create([
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 20,
            'minimum_cart_amount' => 0,
            'active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);

        $cart = Cart::create(['user_id' => $user->id]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'promotion_code' => $promotion->code,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_promotion_is_rejected_when_global_usage_limit_is_reached(): void
    {
        $user = User::factory()->create();

        $promotion = \App\Models\Promotion::factory()->create([
            'code' => 'MAXED',
            'type' => 'fixed',
            'value' => 500,
            'minimum_cart_amount' => 0,
            'usage_limit' => 1,
            'per_customer_usage_limit' => null,
            'active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $otherUser = User::factory()->create();

        $existingOrder = \App\Models\Order::create([
            'user_id' => $otherUser->id,
            'status' => 'pending',
            'subtotal' => 10000,
            'discount' => 500,
            'total' => 9500,
            'promotion_code' => $promotion->code,
        ]);

        \App\Models\PromotionUsage::create([
            'promotion_id' => $promotion->id,
            'user_id' => $otherUser->id,
            'order_id' => $existingOrder->id,
        ]);

        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 10,
        ]);

        $cart = Cart::create(['user_id' => $user->id]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/checkout', [
            'promotion_code' => $promotion->code,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseCount('orders', 1);
    }
}
