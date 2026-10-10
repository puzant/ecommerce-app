<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Promotion::factory()->create([
            'code' => 'SAVE20',
            'type' => 'percentage',
            'value' => 20,
        ]);

        Promotion::factory()->create([
            'code' => 'TAKE5',
            'type' => 'fixed',
            'value' => 500,
        ]);
    }
}
