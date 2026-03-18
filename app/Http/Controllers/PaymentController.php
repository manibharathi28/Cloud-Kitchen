<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Http\Response;

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

    public function index()
    {
        return response()->json(Payment::all(), Response::HTTP_OK);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment, Response::HTTP_OK);
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->json(['message' => 'Payment deleted (soft)'], Response::HTTP_OK);
    }
}
