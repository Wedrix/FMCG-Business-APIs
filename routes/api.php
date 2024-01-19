<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::prefix('summary')->group(function () {
        Route::get('/', [SummaryController::class, 'index']);
    
        Route::get('graph1', [SummaryController::class, 'graph1']);
    
        Route::get('graph2', [SummaryController::class, 'graph2']);

        Route::get('graph3', [SummaryController::class, 'graph3']);

        Route::get('graph4', [SummaryController::class, 'graph4']);
    
        Route::get('audit_logs', [SummaryController::class, 'indexAuditLogs']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);

        Route::get('/deleted', [ProductController::class, 'indexDeleted']);

        Route::post('/', [ProductController::class, 'create']);

        Route::post('/{productId}/restore', [ProductController::class, 'restore']);

        Route::put('/{product}', [ProductController::class, 'update']);

        Route::delete('/{product}', [ProductController::class, 'delete']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);

        Route::get('/deleted', [UserController::class, 'indexDeleted']);

        Route::post('/', [UserController::class, 'create']);

        Route::post('/{userId}/restore', [UserController::class, 'restore']);

        Route::put('/{user}', [UserController::class, 'update']);

        Route::delete('/{user}', [UserController::class, 'delete']);
    });

    Route::prefix('shops')->group(function () {
        Route::get('/', [ShopController::class, 'index']);

        Route::get('/deleted', [ShopController::class, 'indexDeleted']);

        Route::post('/', [ShopController::class, 'create']);

        Route::put('/{shop}', [ShopController::class, 'update']);

        Route::post('/{shopId}/restore', [ShopController::class, 'restore']);

        Route::delete('/{shop}', [ShopController::class, 'delete']);

        Route::get('/{shop}/sales', [ShopController::class, 'indexSales']);

        Route::get('/{shop}/sales/deleted', [ShopController::class, 'indexDeletedSales']);

        Route::post('/{shop}/sales', [ShopController::class, 'createSale']);

        Route::delete('/{shop}/sales/{saleId}', [ShopController::class, 'deleteSale']);

        Route::post('/{shop}/sales/{saleId}/restore', [ShopController::class, 'restoreSale']);

        Route::get('/{shop}/products', [ShopController::class, 'indexProducts']);

        Route::get('/{shop}/expenses', [ShopController::class, 'indexExpenses']);

        Route::post('/{shop}/expenses', [ShopController::class, 'createExpense']);

        Route::delete('/{shop}/expenses/{expenseId}', [ShopController::class, 'deleteExpense']);
    
        Route::post('/{shop}/products', [ShopController::class, 'createProduct']);

        Route::delete('/{shop}/products', [ShopController::class, 'deleteProduct']);
    });

    Route::prefix('receipts')->group(function () {
        Route::get('/', [ReceiptController::class, 'index']);
    });
    
    Route::prefix('sales')->group(function () {
        Route::get('/', [SaleController::class, 'index']);

        Route::get('/deleted', [SaleController::class, 'indexDeleted']);

        Route::post('/{saleId}/restore', [SaleController::class, 'restore']);

        Route::delete('/{sale}', [SaleController::class, 'delete']);
    });

    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
    });

    Route::prefix('audit_logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
    });
});
