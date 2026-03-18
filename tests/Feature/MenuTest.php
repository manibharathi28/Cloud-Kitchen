<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class MenuTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_view_menu_items()
    {
        Menu::factory()->count(5)->create();

        $response = $this->getJson('/api/menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'price',
                        'available',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'pagination'
            ]);
    }

    public function test_can_view_available_menu_items_only()
    {
        Menu::factory()->create(['available' => true]);
        Menu::factory()->create(['available' => false]);

        $response = $this->getJson('/api/menu?available=true');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_authenticated_user_can_create_menu_item()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $menuData = [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
            'price' => $this->faker->randomFloat(2, 5, 50),
            'available' => true,
        ];

        $response = $this->postJson('/api/menu', $menuData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'available',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('menus', [
            'name' => $menuData['name'],
            'price' => $menuData['price']
        ]);
    }

    public function test_unauthenticated_user_cannot_create_menu_item()
    {
        $menuData = [
            'name' => $this->faker->words(3, true),
            'price' => $this->faker->randomFloat(2, 5, 50),
        ];

        $response = $this->postJson('/api/menu', $menuData);

        $response->assertStatus(401);
    }

    public function test_can_update_menu_item()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $menu = Menu::factory()->create();

        $updateData = [
            'name' => 'Updated Menu Item',
            'price' => 25.99,
            'available' => false,
        ];

        $response = $this->putJson("/api/menu/{$menu->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Menu item updated successfully'
            ]);

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'Updated Menu Item',
            'price' => 25.99
        ]);
    }

    public function test_can_delete_menu_item()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $menu = Menu::factory()->create();

        $response = $this->deleteJson("/api/menu/{$menu->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Menu item deleted successfully'
            ]);

        $this->assertSoftDeleted('menus', ['id' => $menu->id]);
    }

    public function test_can_restore_deleted_menu_item()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $menu = Menu::factory()->create();
        $menu->delete();

        $response = $this->postJson("/api/menu/{$menu->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Menu item restored successfully'
            ]);

        $this->assertNotSoftDeleted('menus', ['id' => $menu->id]);
    }
}
