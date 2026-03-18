<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['items.menu', 'user'])
            ->when($request->status, fn($query, $status) => $query->where('status', $status))
            ->when($request->user_id, fn($query, $userId) => $query->where('user_id', $userId))
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ]
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        $order = Order::create([
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'scheduled_for' => $request->scheduled_for,
            'total_amount' => 0,
            'status' => 'pending',
        ]);

        $total = 0;
        foreach ($request->items as $item) {
            $orderItem = new OrderItem([
                'menu_id' => $item['menu_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
            $order->items()->save($orderItem);
            $total += $item['price'] * $item['quantity'];
        }

        $order->update(['total_amount' => $total]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order created successfully',
            'data' => OrderResource::make($order->load(['items.menu', 'user']))
        ], 201);
    }

    public function show(Order $order)
    {
        $order->load(['items.menu', 'user']);
        
        return response()->json([
            'status' => 'success',
            'data' => OrderResource::make($order)
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'nullable|in:pending,preparing,ready,completed,cancelled',
            'scheduled_for' => 'nullable|date|after:now',
        ]);

        $order->update($request->only('status', 'scheduled_for'));

        return response()->json([
            'status' => 'success',
            'message' => 'Order updated successfully',
            'data' => OrderResource::make($order->load(['items.menu', 'user']))
        ]);
    }

    public function destroy(Order $order)
    {
        if ($order->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete completed orders'
            ], 422);
        }

        $order->items()->delete();
        $order->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Order deleted successfully'
        ]);
    }

    public function myOrders(Request $request)
    {
        $orders = Order::with(['items.menu'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ]
        ]);
    }
}
