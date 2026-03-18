<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use Illuminate\Support\Str;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    public function download($order_id){
        $invoice = Invoice::firstOrCreate(
            ['order_id'=>$order_id],
            ['invoice_number'=>Str::upper(Str::random(10)),'issued_at'=>now()]
        );

        // Simulate file path
        $invoice->update(['file_path'=>"invoices/{$invoice->invoice_number}.pdf"]);

        return $invoice;
    }

    public function index()
    {
        return response()->json(Invoice::all(), Response::HTTP_OK);
    }

    public function show(Invoice $invoice)
    {
        return response()->json($invoice, Response::HTTP_OK);
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return response()->json(['message' => 'Invoice deleted (soft)'], Response::HTTP_OK);
    }
}
