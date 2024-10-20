<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::all()->each(function ($user) {
            // Each user will have between 0 and 3 orders
            $ordersCount = rand(0, 3);

            for ($i = 0; $i < $ordersCount; $i++) {
                $order = Order::factory()->create([
                    'user_id' => $user->id,
                ]);

                // Get random products and attach them to the order with random quantities
                $products = Product::inRandomOrder()->take(rand(1, 5))->get();

                foreach ($products as $product) {
                    $order->products()->attach($product->id, ['quantity' => rand(1, 5)]);
                }
            }
        });
    }
}
