<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class OrderTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_authenticated_user_can_create_order()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $menuItems = Menu::factory()->count(3)->create(['available' => true]);

        $orderData = [
            'type' => 'single',
            'items' => [
                [
                    'menu_id' => $menuItems[0]->id,
                    'quantity' => 2,
                    'price' => $menuItems[0]->price,
                ],
                [
                    'menu_id' => $menuItems[1]->id,
                    'quantity' => 1,
                    'price' => $menuItems[1]->price,
                ],
            ],
        ];

        $response = $this->postJson('/api/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'type',
                    'total_amount',
                    'status',
                    'items' => [
                        '*' => [
                            'id',
                            'menu_id',
                            'quantity',
                            'price',
                            'subtotal',
                            'menu'
                        ]
                    ]
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'type' => 'single',
            'status' => 'pending'
        ]);
    }

    public function test_unauthenticated_user_cannot_create_order()
    {
        $response = $this->postJson('/api/orders', [
            'type' => 'single',
            'items' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_can_view_orders()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Order::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'type',
                        'total_amount',
                        'status',
                        'items',
                        'user'
                    ]
                ],
                'pagination'
            ]);
    }

    public function test_can_view_own_orders()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Order::factory()->count(3)->create(['user_id' => $user->id]);
        Order::factory()->count(2)->create(); // Other user's orders

        $response = $this->getJson('/api/my-orders');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_can_update_order_status()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'status' => 'preparing'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Order updated successfully'
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'preparing'
        ]);
    }

    public function test_can_delete_pending_order()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        $response = $this->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Order deleted successfully'
            ]);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_cannot_delete_completed_order()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed'
        ]);

        $response = $this->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Cannot delete completed orders'
            ]);
    }

    public function test_order_validation_requires_items()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'type' => 'single',
            'items' => []
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}
