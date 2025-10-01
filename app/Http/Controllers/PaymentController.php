<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function process(Request $request){
        $request->validate([
            'order_id'=>'required|exists:orders,id',
            'gateway'=>'required|string',
            'amount'=>'required|numeric'
        ]);

        $payment = Payment::create([
            'order_id'=>$request->order_id,
            'gateway'=>$request->gateway,
            'amount'=>$request->amount,
            'status'=>'completed', // simulation
            'transaction_id'=>uniqid()
        ]);

        return $payment;
    }
}
