<?php

use App\Http\Controllers\AuthController;
use App\Models\Audit;
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
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
});

Route::prefix('admin')->middleware('auth:api')->group(function () {
    Route::get('/summary', function () {
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

    Route::get('/summary/graph1', function () {
        Gate::authorize('admin');

        $data = [];

        for ($i = 1; $i < 8; $i++) {
            $data[$i] = Sale::query()
                            ->whereDate('created_at', now()->subDays($i))
                            ->sum('total');
        }

        return $data;
    });

    Route::get('/summary/graph2', function () {
        Gate::authorize('admin');

        $shopIds = Shop::query()->select('id')->get()->toArray();

        return array_map(
            fn(string $shopId) => Sale::query()->whereShopId($shopId)->sum('total'),
            $shopIds
        );
    });

    Route::get('/summary/audits', function () {
        Gate::authorize('admin');

        return Audit::query()->take(12)->get();
    });
});
