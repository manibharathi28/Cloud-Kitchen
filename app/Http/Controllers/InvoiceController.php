<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use Illuminate\Support\Str;

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
}
