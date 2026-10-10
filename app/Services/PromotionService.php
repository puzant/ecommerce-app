<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function validate(
        string $code,
        int $cartAmount,
        User $user
    ): Promotion {
        $promotion = Promotion::where('code', $code)
            ->lockForUpdate()
            ->first();

        if (!$promotion) {
            throw ValidationException::withMessages([
                'code' => ['Invalid promotion code.'],
            ]);
        }

        if (!$promotion->active) {
            throw ValidationException::withMessages([
                'code' => ['This promotion is inactive.'],
            ]);
        }

        if (
            $promotion->starts_at &&
            now()->lt($promotion->starts_at)
        ) {
            throw ValidationException::withMessages([
                'code' => ['This promotion is not active yet.'],
            ]);
        }

        if (
            $promotion->ends_at &&
            now()->gt($promotion->ends_at)
        ) {
            throw ValidationException::withMessages([
                'code' => ['This promotion has expired.'],
            ]);
        }

        if (
            $promotion->minimum_cart_amount &&
            $cartAmount < $promotion->minimum_cart_amount
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'Cart total does not meet the minimum amount for this promotion.'
                ],
            ]);
        }

        // Total promotion usage limit
        if (
            $promotion->usage_limit !== null &&
            $promotion->usages()->count() >= $promotion->usage_limit
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'This promotion has reached its usage limit.'
                ],
            ]);
        }

        // Per-customer promotion usage limit
        if (
            $promotion->per_customer_usage_limit !== null &&
            $promotion->usages()
                ->where('user_id', $user->id)
                ->count() >= $promotion->per_customer_usage_limit
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'You have reached the usage limit for this promotion.'
                ],
            ]);
        }

        return $promotion;
    }
}