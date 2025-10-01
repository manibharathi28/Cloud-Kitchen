<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;

// Auth
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('menu', MenuController::class);
    Route::apiResource('orders', OrderController::class);

    Route::post('payments', [PaymentController::class, 'process']);
    Route::get('invoices/{order_id}', [InvoiceController::class, 'download']);
    Route::get('reports/sales-summary', [ReportController::class, 'salesSummary']);
});

Route::get('/test', function () {
    return response()->json(['message' => 'API works!']);
});
