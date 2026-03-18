<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_be_cancelled()
    {
        $order = Order::factory()->create(['status' => 'pending']);
        
        $this->assertTrue($order->canBeCancelled());
    }

    public function test_completed_order_cannot_be_cancelled()
    {
        $order = Order::factory()->create(['status' => 'completed']);
        
        $this->assertFalse($order->canBeCancelled());
    }

    public function test_cancelled_order_cannot_be_cancelled()
    {
        $order = Order::factory()->create(['status' => 'cancelled']);
        
        $this->assertFalse($order->canBeCancelled());
    }

    public function test_formatted_total_attribute()
    {
        $order = Order::factory()->create(['total_amount' => 25.99]);
        
        $this->assertEquals('$25.99', $order->formatted_total);
    }

    public function test_order_has_user_relationship()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        
        $this->assertInstanceOf(User::class, $order->user);
        $this->assertEquals($user->id, $order->user->id);
    }
}
