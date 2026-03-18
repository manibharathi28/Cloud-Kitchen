<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;

// Public routes
Route::get('/test', function () {
    return response()->json(['message' => 'API works!']);
});

Route::get('/menu', [MenuController::class, 'index']);
Route::get('/health', [HealthController::class, 'index']);
Route::get('/health/detailed', [HealthController::class, 'detailed']);
Route::get('/ping', [HealthController::class, 'ping']);

// Auth routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);
    
    // Menu management
    Route::apiResource('menu', MenuController::class);
    Route::post('menu/{id}/restore', [MenuController::class, 'restore']);
    
    // Order management
    Route::apiResource('orders', OrderController::class);
    Route::get('my-orders', [OrderController::class, 'myOrders']);
    
    // Payment management
    Route::post('payments', [PaymentController::class, 'process']);
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy']);
    
    // Invoice management
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('invoices/{order_id}/download', [InvoiceController::class, 'download']);
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);
    
    // Reports
    Route::get('reports/sales-summary', [ReportController::class, 'salesSummary']);
});

