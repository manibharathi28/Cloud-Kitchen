<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;

use Illuminate\Http\Request;
// Route::get('/login', function (Request $request) {
//     return response()->json([
//         'message' => 'Please use POST method to login.'
//     ], 405);
// })->name('login');

Route::get('/', function () {
    return response()->json([
        'message' => 'Cloud Kitchen API',
        'version' => '1.0.0',
        'status' => 'running'
    ]);
});

Route::get('/test', function () {
    return response()->json(['message' => 'API works in browser!']);
});

// Public Auth Routes (api)
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// All API routes moved to api.php for consistency
