<?php

use App\Http\Controllers\AuthController;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\SMS\SMS;
use App\TextMessages\LoginCredentialsTextMessage;
use App\TextMessages\NewSaleTextMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
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
    
            for ($i = 6; $i >= 0; $i--) {
                $dayOfWeek = now()->subDays($i)->shortLocaleDayOfWeek;

                $data[$dayOfWeek] = Sale::query()
                                        ->whereDate('created_at', now()->subDays($i))
                                        ->sum('total');
            }
    
            return $data;
        });
    
        Route::get('graph2', function () {
            Gate::authorize('admin');
    
            $shops = Shop::query()->select('id','name')->get()->toArray();
    
            $data = [];

            foreach ($shops as $shop) {
                $data[$shop['name']] = Sale::query()
                                            ->where('shop_id', $shop['id'])
                                            ->sum('total');
            }

            return $data;
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

            $data = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|min:0|max:1000000000',
                'quantity' => 'required|numeric|min:0|max:1000000000',
                'description' => 'string|nullable|max:1500'
            ]);

            $product = new Product($data);

            $product->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Create Product",
                    'description' => "Created '$product->name'"
                ])
            )
            ->save();

            return $product;
        });

        Route::post('/{productId}/restore', function (Request $request, $productId) {
            Gate::authorize('admin');

            $product = Product::onlyTrashed()->findOrFail($productId);

            $product->restore();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Restore Product",
                    'description' => "Restored '$product->name'"
                ])
            )
            ->save();

            return $product;
        });

        Route::put('/{product}', function (Request $request, Product $product) {
            Gate::authorize('admin');

            $data = $request->validate([
                'name' => 'string|max:255',
                'price' => 'numeric|min:0|max:1000000000',
                'quantity' => 'numeric|min:0|max:1000000000',
                'description' => 'string|nullable|max:1500'
            ]);

            $product->name = $data['name'] ?? $product->name;
            $product->price = $data['price'] ?? $product->price;
            $product->quantity = $data['quantity'] ?? $product->quantity;
            $product->description = $data['description'] ?? $product->description;

            $product->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Update Product",
                    'description' => "Updated '$product->name'"
                ])
            )
            ->save();

            return $product;
        });

        Route::delete('/{product}', function (Request $request, Product $product) {
            Gate::authorize('admin');

            $product->delete();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Delete Product",
                    'description' => "Deleted '$product->name'"
                ])
            )
            ->save();

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

            $data = $request->validate([
                'full_name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:255',
                'username' => 'required|string|max:255',
                'password' => 'required|string|min:5',
                'role' => 'required|in:admin,super_admin,sales_man',
                'shop_id' => 'required_if:role,sales_man|numeric|exists:shops,id'
            ]);

            $user = new User($data);

            $user->password = Hash::make($data['password']);

            $user->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Create User",
                    'description' => "Created '$user->full_name' with role '$user->role'"
                ])
            )
            ->save();

            SMS::from('Storekd Inc') //TODO: Change to Eben Gen after approval
                ->send(
                    new LoginCredentialsTextMessage(
                        user: $user,
                        password: $data['password']
                    )
                );

            return $user;
        });

        Route::post('/{userId}/restore', function (Request $request, $userId) {
            Gate::authorize('super_admin');

            $user = User::onlyTrashed()->findOrFail($userId);

            $user->restore();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Restore User",
                    'description' => "Restored '$user->full_name'"
                ])
            )
            ->save();

            return $user;
        });

        Route::put('/{user}', function (Request $request, User $user) {
            Gate::authorize('super_admin');

            $data = $request->validate([
                'full_name' => 'string|max:255',
                'phone_number' => 'string|max:255',
                'username' => 'string|max:255',
                'password' => 'string|min:5',
                'role' => 'in:admin,super_admin,sales_man',
                'shop_id' => 'required_if:role,sales_man|numeric|exists:shops,id'
            ]);

            $user->full_name = $data['full_name'] ?? $user->full_name;
            $user->phone_number = $data['phone_number'] ?? $user->phone_number;
            $user->username = $data['username'] ?? $user->username;
            $user->password = isset($data['password']) ? Hash::make($data['password']) : $user->password;
            $user->role = $data['role'] ?? $user->role;
            $user->shop_id = $data['shop_id'] ?? $user->shop_id;

            $user->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Update User",
                    'description' => "Updated '$user->full_name'"
                ])
            )
            ->save();

            if (isset($data['password'])) {
                SMS::from('Storekd Inc') //TODO: Change to Eben Gen after approval
                    ->send(
                        new LoginCredentialsTextMessage(
                            user: $user,
                            password: $data['password']
                        )
                    );
            }

            return $user;
        });

        Route::delete('/{user}', function (Request $request, User $user) {
            Gate::authorize('super_admin');

            $user->delete();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Delete User",
                    'description' => "Deleted '$user->full_name'"
                ])
            )
            ->save();

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

        Route::get('/{shop}/sales', function (Request $request, Shop $shop) {
            $beforeDate = now()->subDays(7);
            
            return $shop->sales()->where('created_at','>=',$beforeDate)->get();
        });

        Route::post('/', function (Request $request) {
            Gate::authorize('admin');

            $data = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string|max:255'
            ]);

            $shop = new Shop($data);

            $shop->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Create Shop",
                    'description' => "Created '$shop->name'"
                ])
            )
            ->save();

            return $shop;
        });

        Route::put('/{shop}', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            $data = $request->validate([
                'name' => 'string|max:255',
                'address' => 'string|max:255'
            ]);

            $shop->name = $data['name'] ?? $shop->name;
            $shop->address = $data['address'] ?? $shop->address;

            $shop->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Update Shop",
                    'description' => "Updated '$shop->name'"
                ])
            )
            ->save();

            return $shop;
        });

        Route::post('/{shopId}/restore', function (Request $request, $shopId) {
            Gate::authorize('admin');

            $shop = Shop::onlyTrashed()->findOrFail($shopId);

            $shop->restore();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Restore Shop",
                    'description' => "Restored '$shop->name'"
                ])
            )
            ->save();

            return $shop;
        });

        Route::delete('/{shop}', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            $shop->delete();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Delete Shop",
                    'description' => "Deleted '$shop->name'"
                ])
            )
            ->save();

            return response('success', 200);
        });
        Route::get('/{shop}/products', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            return $shop->products;
        });
    


        Route::post('/{shop}/products', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            // TODO: Validate Data:
                // Check product ids exist
                // Verify quantity available

            // TODO: Add Shop Products

            // TODO: Add Audit Log for each entry

            // return shop

            return $shop;
        });
    });

    Route::prefix('receipts')->group(function () {
        Route::get('/', function () {
            return Receipt::with(['sales' => function ($query) {
                $query->withThrashed();
            }])
            ->get();
        });
    });
    
    Route::prefix('sales')->group(function () {
        Route::get('/', function () {
            return Sale::all();
        });

        Route::get('/deleted', function () {
            return Sale::onlyTrashed()->get();
        });

        Route::post('/',function(Request $request) {
            $data = $request->validate([
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:255',
                'shop_id'=> 'required|exists:shops,id',
                'products' => 'required'
            ]);

            $shop = Shop::find($data['shop_id']);
            $receipt = new Receipt($data);

            foreach ($data['products'] as $shop_product){ 
                $productId = $shop_product['id'];
                $quantity = $shop_product['quantity'];
                $discount = $shop_product['discount'];
                
                $product = $shop->products()->findOrFail($productId);
            
                if ($product->quantity < $quantity){
                    return response("The requested quantity is not available for $product->name", 422);

                }

                $sale = new Sale();
                $sale->product_id = $productId;
                $sale->quantity = $quantity;
                $sale->discount = $discount;
                $sale->shop_id = $shop->id;
                $sale->user_id = $request->user()->id;

                $receipt->sales[] = $sale;
    
            }
           
            $receipt->push();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Create Sale",
                    'description' => "Created sale with receipt #'$receipt->id'"
                ])
            )
            ->save();

            SMS::from('Storekd Inc') //TODO: Change to Eben Gen after approval
                ->send(
                    new NewSaleTextMessage(
                        receipt:$receipt,
                    )
                );

            return $receipt;
            
        });

        Route::post('/{saleId}/restore', function (Request $request, $saleId) {
            Gate::authorize('admin');

            $sale = Sale::onlyTrashed()->findOrFail($saleId);

            $sale->restore();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Restore Sale",
                    'description' => "Restored sale #'$sale->id'"
                ])
            )
            ->save();

            return $sale;
        });

        Route::delete('/{sale}', function (Request $request, Sale $sale) {
            Gate::authorize('admin');

            $sale->delete();

            $sale->product->quantity += $sale->quantity;

            $sale->product->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Delete Sale",
                    'description' => "Deleted sale #'$sale->id'"
                ])
            )
            ->save();

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
