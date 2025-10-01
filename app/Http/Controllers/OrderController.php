<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;

class OrderController extends Controller
{
    public function index(){ return Order::with('items')->get(); }

    public function store(Request $request){
        $request->validate([
            'user_id'=>'required|exists:users,id',
            'type'=>'required|in:single,bulk',
            'scheduled_for'=>'nullable|date',
            'items'=>'required|array'
        ]);

        $order = Order::create([
            'user_id'=>$request->user_id,
            'type'=>$request->type,
            'scheduled_for'=>$request->scheduled_for,
            'total_amount'=>0,
            'status'=>'pending'
        ]);

        $total = 0;
        foreach($request->items as $item){
            $orderItem = new OrderItem([
                'menu_id'=>$item['menu_id'],
                'quantity'=>$item['quantity'],
                'price'=>$item['price']
            ]);
            $order->items()->save($orderItem);
            $total += $item['price']*$item['quantity'];
        }

        $order->update(['total_amount'=>$total]);
        return $order->load('items');
    }

    public function show(Order $order){ return $order->load('items'); }

    public function update(Request $request, Order $order){
        $order->update($request->all());
        return $order;
    }

    public function destroy(Order $order){
        $order->delete();
        return response()->json(['message'=>'Order deleted']);
    }
}
