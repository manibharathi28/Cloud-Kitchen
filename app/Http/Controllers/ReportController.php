<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function salesSummary(){
        $totalOrders = Order::count();
        $totalRevenue = Order::sum('total_amount');
        $bulkOrders = Order::where('type','bulk')->count();
        return response()->json([
            'total_orders'=>$totalOrders,
            'total_revenue'=>$totalRevenue,
            'bulk_orders'=>$bulkOrders
        ]);
    }
}
