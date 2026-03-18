<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create users
        User::factory(10)->create();

        // Create a test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create menu items
        Menu::factory(20)->create();

        // Create sample orders with items
        Order::factory(15)->create()->each(function ($order) {
            $menuItems = Menu::inRandomOrder()->take(rand(1, 4))->get();
            
            $total = 0;
            foreach ($menuItems as $menuItem) {
                $quantity = rand(1, 3);
                $price = $menuItem->price;
                $subtotal = $quantity * $price;
                $total += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $menuItem->id,
                    'quantity' => $quantity,
                    'price' => $price,
                ]);
            }

            $order->update(['total_amount' => $total]);
        });

        $this->command->info('Database seeded successfully!');
        $this->command->info('Users: 11');
        $this->command->info('Menu Items: 20');
        $this->command->info('Orders: 15');
    }
}
