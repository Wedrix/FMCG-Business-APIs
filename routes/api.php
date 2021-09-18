<?php

use App\Http\Controllers\AuthController;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        Route::get('/', function () {
            Gate::authorize('admin');
    
            return [
                'total_products' => Product::query()->count(),
                'total_shops' => Shop::query()->count(),
                'total_users' => User::query()->count(),
                'total_month_sales' => Sale::query()
                                            ->whereYear('created_at', now()->year())
                                            ->whereMonth('created_at', now()->month())
                                            ->sum('total'),
            ];
        });
    
        Route::get('graph1', function () {
            Gate::authorize('admin');
    
            $data = [];
    
            for ($i = 1; $i < 8; $i++) {
                $data[$i] = Sale::query()
                                ->whereDate('created_at', now()->subDays($i))
                                ->sum('total');
            }
    
            return $data;
        });
    
        Route::get('graph2', function () {
            Gate::authorize('admin');
    
            $shopIds = Shop::query()->select('id')->get()->toArray();
    
            return array_map(
                fn(string $shopId) => Sale::query()->whereShopId($shopId)->sum('total'),
                $shopIds
            );
        });
    
        Route::get('audit_logs', function () {
            Gate::authorize('admin');
    
            return AuditLog::query()->latest()->take(12)->get();
        });
    });

    Route::prefix('products')->group(function () {
        Route::get('/', function () {
            Gate::authorize('admin');

            return Product::all();
        });

        Route::get('/deleted', function () {
            Gate::authorize('admin');

            return Product::onlyTrashed()->get();
        });

        Route::post('/', function (Request $request) {
            Gate::authorize('admin');

            // TODO: Validate Request

            // TODO: Create Product

            // TODO: Add Audit Log

            // TODO: Return Product
        });

        Route::post('/{productId}/restore', function (Request $request, $productId) {
            Gate::authorize('admin');

            $product = Product::onlyTrashed()->findOrFail($productId);

            $product->restore();

            // TODO: Add Audit Log

            return $product;
        });

        Route::put('/{product}', function (Request $request, Product $product) {
            Gate::authorize('admin');

            // TODO: Validate Request

            // TODO: Update Product

            // TODO: Add Audit Log

            return $product;
        });

        Route::delete('/{product}', function (Request $request, Product $product) {
            Gate::authorize('admin');

            $product->delete();

            // TODO: Add Audit Log

            return response('success', 200);
        });
    });

    Route::prefix('users')->group(function () {
        Route::get('/', function () {
            Gate::authorize('super_admin');

            return User::all();
        });

        Route::get('/deleted', function () {
            Gate::authorize('super_admin');

            return User::onlyTrashed()->get();
        });

        Route::post('/', function (Request $request) {
            Gate::authorize('super_admin');

            // TODO: Validate Request

            // TODO: Create User

            // TODO: Generate Temp Passowrd

            // TODO: Add User

            // TODO: SMS User temp password

            // TODO: Add Audit Log

            // TODO: return user
        });

        Route::post('/{userId}/restore', function (Request $request, $userId) {
            Gate::authorize('super_admin');

            $user = User::onlyTrashed()->findOrFail($userId);

            $user->restore();

            // TODO: Add Audit Log

            return $user;
        });

        Route::put('/{user}', function (Request $request, User $user) {
            Gate::authorize('super_admin');

            // TODO: Validate Request

            // Update User

            // TODO: Add Audit Log

            return $user;
        });

        Route::delete('/{user}', function (Request $request, User $user) {
            Gate::authorize('super_admin');

            $user->delete();

            // TODO: Add Audit Log

            return response('success', 200);
        });
    });

    Route::prefix('shops')->group(function () {
        Route::get('/', function () {
            Gate::authorize('admin');

            return Shop::withCount('products')->get();
        });

        Route::get('/deleted', function () {
            Gate::authorize('admin');

            return Shop::onlyTrashed()->get();
        });

        Route::post('/', function (Request $request) {
            Gate::authorize('admin');

            // TODO: Validate Request

            // TODO: Create Shop

            // TODO: Add Audit Log

            // TODO: Return Shop
        });

        Route::put('/{shop}', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            // TODO: Validate Request

            // TODO: Update User

            // TODO: Add Audit Log

            return $shop;
        });

        Route::post('/{shopId}/restore', function (Request $request, $shopId) {
            Gate::authorize('admin');

            $shop = Shop::onlyTrashed()->findOrFail($shopId);

            $shop->restore();

            // TODO: Add Audit Log

            return $shop;
        });

        Route::delete('/{shop}', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            $shop->delete();

            // TODO: Add Audit Log

            return response('success', 200);
        });
    });
    
    Route::prefix('sales')->group(function () {
        Route::get('/', function () {
            Gate::authorize('admin');

            return Sale::all();
        });

        Route::get('/deleted', function () {
            Gate::authorize('admin');

            return Sale::onlyTrashed()->get();
        });

        Route::post('/{saleId}/restore', function (Request $request, $saleId) {
            Gate::authorize('admin');

            $sale = Sale::onlyTrashed()->findOrFail($saleId);

            $sale->restore();

            // TODO: Add Audit Log

            return $sale;
        });

        Route::put('/{sale}', function (Request $request, Sale $sale) {
            Gate::authorize('admin');

            // TODO: Validate Request

            // TODO: Update Sale

            // TODO: Add Audit Log

            return $sale;
        });

        Route::delete('/{sale}', function (Request $request, Sale $sale) {
            Gate::authorize('admin');

            $sale->delete();

            // TODO: Add Audit Log

            return response('success', 200);
        });
    });

    Route::prefix('audit_logs')->group(function () {
        Route::get('/', function () {
            Gate::authorize('admin');

            return AuditLog::all();
        });
    });
});
