<?php

use App\Http\Controllers\AuthController;
use App\Models\AuditLog;
use App\Models\Expense;
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
                'total_month_income' => (function () {
                    $today = now();
                    $year = $today->year;
                    $month = $today->month;

                    $totalSales = Sale::query()
                                    ->whereYear('created_at', $year)
                                    ->whereMonth('created_at', $month)
                                    ->sum('total');
        
                    $totalExpenses = Expense::query()
                                            ->whereYear('created_at', $year)
                                            ->whereMonth('created_at', $month)
                                            ->sum('amount');

                    $totalIncome = $totalSales - $totalExpenses;

                    return $totalIncome;
                })(),
            ];
        });
    
        // Total Daily Sales For The Week
        Route::get('graph1', function () {
            Gate::authorize('admin');
    
            $data = [];
    
            for ($i = 6; $i >= 0; $i--) {
                $dayOfWeek = now()->subDays($i)->locale('en')->minDayName;

                $data[$dayOfWeek] = Sale::query()
                                        ->whereDate('created_at', now()->subDays($i))
                                        ->sum('total');
            }
    
            return $data;
        });
    
        // Today's Shops' Total Sales
        Route::get('graph2', function () {
            Gate::authorize('admin');

            $today = now();
    
            $shops = Shop::query()
                        ->select('id','name')
                        ->get()
                        ->toArray();
    
            $data = [];

            foreach ($shops as $shop) {
                $data[$shop['name']] = Sale::query()
                                            ->where('shop_id', $shop['id'])
                                            ->whereDate('created_at', $today)
                                            ->sum('total');
            }

            return $data;
        });

        // Today's Shops' Total Expenses
        Route::get('graph3', function () {
            Gate::authorize('admin');

            $today = now();
    
            $shops = Shop::query()
                        ->select('id','name')
                        ->get()
                        ->toArray();
    
            $data = [];

            foreach ($shops as $shop) {
                $data[$shop['name']] = Expense::query()
                                            ->where('shop_id', $shop['id'])
                                            ->whereDate('created_at', $today)
                                            ->sum('amount');
            }

            return $data;
        });

        // Today's Shops' Total Income
        Route::get('graph4', function () {
            Gate::authorize('admin');

            $today = now();
    
            $shops = Shop::query()
                        ->select('id','name')
                        ->get()
                        ->toArray();
    
            $data = [];

            foreach ($shops as $shop) {
                $totalSales = Sale::query()
                                ->where('shop_id', $shop['id'])
                                ->whereDate('created_at', $today)
                                ->sum('total');

                $totalExpenses = Expense::query()
                                        ->where('shop_id', $shop['id'])
                                        ->whereDate('created_at', $today)
                                        ->sum('amount');

                $totalIncome = $totalSales - $totalExpenses;

                $data[$shop['name']] = $totalIncome;
            }

            return $data;
        });
    
        Route::get('audit_logs', function () {
            Gate::authorize('admin');
    
            return AuditLog::query()
                        ->with('user:id,full_name,username')
                        ->latest()
                        ->take(12)
                        ->get();
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

            $product = Product::onlyTrashed()->find($productId);

            if (is_null($product)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "No deleted product with id '$productId' exists"
                    ],
                    status:422
                );
            }

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

            return [
                "message" => "Success!"
            ];
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
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:5',
                'role' => 'required|in:admin,super_admin,sales_man',
                'shop_id' => 'required_if:role,sales_man'
            ]);

            if (isset($data['shop_id']) && is_null(Shop::find($data['shop_id']))) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "The shop with id '{$data['shop_id']}' does not exist"
                    ],
                    status:422
                );
            }

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

            SMS::send(
                    new LoginCredentialsTextMessage(
                        user: $user,
                        password: $data['password']
                    )
                );

            return $user;
        });

        Route::post('/{userId}/restore', function (Request $request, $userId) {
            Gate::authorize('super_admin');

            $user = User::onlyTrashed()->find($userId);

            if (is_null($user)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "No deleted user with id '$userId' exists"
                    ],
                    status:422
                );
            }

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
                'shop_id' => 'required_if:role,sales_man'
            ]);

            if (isset($data['shop_id']) && is_null(Shop::find($data['shop_id']))) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "The shop with id '{$data['shop_id']}' does not exist"
                    ],
                    status:422
                );
            }

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
                SMS::send(
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

            return [
                "message" => "Success!"
            ];
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

            $shop = Shop::onlyTrashed()->find($shopId);

            if (is_null($shop)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "No deleted shop with id '$shopId' exists"
                    ],
                    status:422
                );
            }

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

            return [
                "message" => "Success!"
            ];
        });

        Route::get('/{shop}/sales', function (Request $request, Shop $shop) {
            Gate::authorize('sales_man', $shop->id);

            $beforeDate = now()->subDays(7);
            
            return $shop->sales()
                        ->with('shop:id,name')
                        ->with('product:id,name,price')
                        ->with('user:id,full_name,username')
                        ->where('created_at','>=',$beforeDate)
                        ->get();
        });

        Route::get('/{shop}/sales/deleted', function (Request $request, Shop $shop) {
            Gate::authorize('sales_man', $shop->id);

            return $shop->sales()
                        ->onlyTrashed()
                        ->with('shop:id,name')
                        ->with('product:id,name,price')
                        ->with('user:id,full_name,username')
                        ->get();
        });

        Route::post('/{shop}/sales', function (Request $request, Shop $shop) {
            Gate::authorize('sales_man', $shop->id);

            $data = $request->validate([
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:255',
                'products' => 'required|array',
                'products.*.id' => [
                    'required',
                    function ($attribute, $value, $fail) use ($shop) {
                        if (is_null($shop->products()->find($value))) {
                            $fail("$shop->name does not have any product with id '$value'");
                        }
                    },
                ],
                'products.*.quantity' => 'required|numeric|max:1000000'
            ]);

            foreach ($data['products'] as $index => $shop_product) { 
                $productId = $shop_product['id'];
                $quantity = $shop_product['quantity'];
                
                $shopProduct = $shop->products()->find($productId);
            
                if ($shopProduct->pivot->quantity < $quantity) {
                    return response()->json(
                        data: [
                            "message" => "The given data was invalid",
                            "error" => [
                                "products.$index.id" => "Only {$shopProduct->pivot->quantity} units available for this product. 
                                $quantity requested."
                            ]
                        ],
                        status:422
                    );
                }
            }
            
            $receipt = new Receipt;
            $receipt->customer_name = $data['customer_name'];
            $receipt->customer_phone = $data['customer_phone'];
            $receipt->save();

            foreach ($data['products'] as $shop_product) { 
                $productId = $shop_product['id'];
                $quantity = $shop_product['quantity'];
                $discount = $shop_product['discount'] ?? 0;
                
                $shopProduct = $shop->products()->find($productId);

                $sale = new Sale();
                $sale->product_id = $productId;
                $sale->receipt_id = $receipt->id;
                $sale->quantity = $quantity;
                $sale->discount = $discount;
                $sale->shop_id = $shop->id;
                $sale->user_id = $request->user()->id;
                $sale->total = (($shopProduct->price * $quantity) - $discount);
                $sale->save();

                $shopProduct->pivot->quantity -= $quantity;
                $shopProduct->pivot->save();
            }

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Create Sale",
                    'description' => "Created sale with receipt #'$receipt->id'"
                ])
            )
            ->save();

            SMS::send(
                    new NewSaleTextMessage(
                        receipt:$receipt,
                    )
                );

            return $receipt;
        });

        Route::delete('/{shop}/sales/{saleId}', function (Request $request, Shop $shop, $saleId) {
            Gate::authorize('sales_man', $shop->id);

            $sale = $shop->sales()->find($saleId);

            if (is_null($sale)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "No sale with id '$saleId' exists for $shop->name"
                    ],
                    status:422
                );
            }

            $shopProduct = $shop->products()->where('product_id', $sale->product->id)->first();

            $shopProduct->pivot->quantity += $sale->quantity;

            $shopProduct->pivot->save();

            $sale->delete();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Delete Sale",
                    'description' => "Deleted sale #'$sale->id'"
                ])
            )
            ->save();

            return [
                "message" => "Success!"
            ];
        });

        Route::post('/{shop}/sales/{saleId}/restore', function (Request $request, Shop $shop, $saleId) {
            Gate::authorize('sales_man', $shop->id);

            $sale = $shop->sales()
                        ->with(['product' => function ($query) {
                            $query->withTrashed();
                        }])
                        ->with(['shop' => function ($query) {
                            $query->withTrashed();
                        }])
                        ->onlyTrashed()
                        ->find($saleId);

            if (is_null($sale)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "No deleted sale with id '$saleId' exists for $shop->name"
                    ],
                    status:422
                );
            }

            if ($sale->product->trashed()) {
                return response()->json(
                    data: [
                        "message" => "Product Unavailable",
                        "error" => "{$sale->product->name} is no longer available for $shop->name"
                    ],
                    status:422
                );
            }

            $shopProduct = $shop->products()->find($sale->product->id);

            if ($shopProduct->pivot->quantity < $sale->quantity) {
                return response()->json(
                    data: [
                        "message" => "Not Enough Inventory",
                        "error" => "$shop->name no longer has enough inventory for {$sale->product->name} to fulfil this order"
                    ],
                    status:422
                );
            }

            $shopProduct->pivot->quantity -= $sale->quantity;
            $shopProduct->pivot->save();

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

        Route::get('/{shop}/products', function (Request $request, Shop $shop) {
            Gate::authorize('sales_man', $shop->id);

            return $shop->products;
        });

        Route::get('/{shop}/expenses', function (Request $request, Shop $shop) {
            Gate::authorize('sales_man', $shop->id);

            return $shop->expenses;
        });

        Route::post('/{shop}/expenses', function (Request $request, Shop $shop) {
            Gate::authorize('sales_man', $shop->id);

            $data = $request->validate([
                'amount' => 'required|numeric|max:1000000',
                'purpose' => 'required|string|max:255'
            ]);

            $todaysShopIncome = (function () use ($shop) {
                $today = now();

                $todaysShopSales = Sale::query()
                                        ->where('shop_id', $shop->id)
                                        ->whereDate('created_at', $today)
                                        ->sum('total');

                $todaysShopExpenses = Expense::query()
                                            ->where('shop_id', $shop->id)
                                            ->whereDate('created_at', $today)
                                            ->sum('amount');

                $income = $todaysShopSales - $todaysShopExpenses;

                return $income;
            })();

            if ($todaysShopIncome < $data['amount']) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "{$data['amount']} is more than today's income for $shop->name"
                    ],
                    status:422
                );
            }

            $expense = new Expense($data);
            $expense->user_id = $request->user()->id;
            $expense->shop_id = $shop->id;
            $expense->save();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Create Expense",
                    'description' => "Created expense for {$expense->shop->name}. Purpose: $expense->purpose, Amount: $expense->amount"
                ])
            )
            ->save();

            return $expense;
        });

        Route::delete('/{shop}/expenses/{expenseId}', function (Request $request, Shop $shop, $expenseId) {
            Gate::authorize('sales_man', $shop->id);

            $expense = $shop->expenses()->find($expenseId);

            if (is_null($expense)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "No expense with id '$expenseId' exists for $shop->name"
                    ],
                    status:422
                );
            }

            $expense->delete();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Deleted Expense",
                    'description' => "Deleted expense for {$expense->shop->name}. Purpose: $expense->purpose, Amount: $expense->amount"
                ])
            )
            ->save();

            return [
                "message" => "Success!"
            ];
        });
    
        Route::post('/{shop}/products', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            $data = $request->validate([
                'products' => 'required|array',
                'products.*.id' => 'required',
                'products.*.quantity' => 'required|numeric|max:1000000000'
            ]);

            foreach ($data['products'] as $index => $shop_product) {
                $productId = $shop_product['id'];
                $quantity = $shop_product['quantity'];

                $product = Product::find($productId);

                if ($product->quantity < $quantity) {
                    return response()->json(
                        data: [
                            "message" => "The given data was invalid",
                            "error" => [
                                "products.$index.quantity" => "Only $product->quantity units available for this product. 
                                $quantity requested."
                            ]
                        ],
                        status:422
                    );
                }
            }

            foreach ($data['products'] as $index => $shop_product) {
                $productId = $shop_product['id'];
                $quantity = $shop_product['quantity'];

                $product = Product::find($productId);

                $shopProduct = $shop->products()->find($productId);

                // If shop product is not available, we create the relationship,
                // else we add the quantity
                if (is_null($shopProduct)) {
                    $shop->products()
                        ->attach([
                            $productId => ['quantity' => $quantity]
                        ]);
                }
                else {
                    $shopProduct->pivot->quantity += $quantity;

                    $shopProduct->pivot->save();
                }

                $product->quantity -= $quantity;

                $product->save();

                (
                    new AuditLog([
                        'user_id' => $request->user()->id,
                        'operation' => "Add Shop Product",
                        'description' => "Added $product->name to $shop->name"
                    ])
                )
                ->save();
            }

            return $shop->products;
        });

        Route::delete('/{shop}/products', function (Request $request, Shop $shop) {
            Gate::authorize('admin');

            $data = $request->validate([
                'products' => 'required|array',
                'products.*.id' => 'required',
                'products.*.quantity' => 'numeric|max:1000000000'
            ]);

            foreach ($data['products'] as $index => $shop_product) {
                $productId = $shop_product['id'];
                $quantity = $shop_product['quantity'] ?? null;

                $shopProduct = $shop->products()->find($productId);

                if (!is_null($quantity)) {
                    if ($shopProduct->quantity < $quantity) {
                        return response()->json(
                            data: [
                                "message" => "The given data was invalid",
                                "error" => [
                                    "products.$index.quantity" => "Only $shopProduct->quantity units available for this product. 
                                    $quantity requested."
                                ]
                            ],
                            status:422
                        );
                    }
                }
            }

            foreach ($data['products'] as $index => $shop_product) {
                $productId = $shop_product['id'];
                $quantity = $shop_product['quantity'] ?? null;

                $product = Product::find($productId);

                $shopProduct = $shop->products()->find($productId);

                if (!is_null($shopProduct)) {
                    if (!is_null($quantity) && ($quantity < $shopProduct->pivot->quantity)) {
                        $shopProduct->pivot->quantity -= $quantity;
                        $shopProduct->pivot->save();

                        $product->quantity += $quantity;
                        $product->save();
                    }
                    else {
                        $product->quantity += $shopProduct->pivot->quantity;
                        $product->save();
        
                        $shop->products()
                            ->detach($productId);
                    }

                    (
                        new AuditLog([
                            'user_id' => $request->user()->id,
                            'operation' => "Delete Shop Product",
                            'description' => "Removed $product->name from $shop->name"
                        ])
                    )
                    ->save();
                }
            }

            return [
                "message" => "Success!"
            ];
        });
    });

    Route::prefix('receipts')->group(function () {
        Route::get('/', function () {
            Gate::authorize('admin');

            return Receipt::query()
                        ->with('sales', function ($query) {
                            $query->withTrashed()
                                ->with('shop:id,name')
                                ->with('product:id,name,price')
                                ->with('user:id,full_name,username');
                        })
                        ->get();
        });
    });
    
    Route::prefix('sales')->group(function () {
        Route::get('/', function () {
            Gate::authorize('admin');

            return Sale::query()
                        ->with('shop:id,name')
                        ->with('product:id,name,price')
                        ->with('user:id,full_name,username')
                        ->get();
        });

        Route::get('/deleted', function () {
            Gate::authorize('admin');

            return Sale::onlyTrashed()
                        ->with('shop:id,name')
                        ->with('product:id,name,price')
                        ->with('user:id,full_name,username')
                        ->get();
        });

        Route::post('/{saleId}/restore', function (Request $request, $saleId) {
            Gate::authorize('admin');

            $sale = Sale::query()
                        ->with(['product' => function ($query) {
                            $query->withTrashed();
                        }])
                        ->with(['shop' => function ($query) {
                            $query->withTrashed();
                        }])
                        ->onlyTrashed()
                        ->find($saleId);

            if (is_null($sale)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "No deleted sale with id '$saleId' exists"
                    ],
                    status:422
                );
            }

            if ($sale->shop->trashed()) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "Shop '{$sale->shop->name}' is no longer available"
                    ],
                    status:422
                );
            }

            if ($sale->product->trashed()) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "Product '{$sale->product->name}' is no longer available for {$sale->shop->name}"
                    ],
                    status:422
                );
            }

            $shopProduct = $sale->shop->products()->find($sale->product->id);

            if ($shopProduct->pivot->quantity < $sale->quantity) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => "{$sale->shop->name} no longer has enough inventory for {$sale->product->name} to fulfil this order"
                    ],
                    status:422
                );
            }

            $shopProduct->pivot->quantity -= $sale->quantity;
            $shopProduct->pivot->save();

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

            $shopProduct = $sale->shop->products()->where('product_id', $sale->product->id)->first();

            $shopProduct->pivot->quantity += $sale->quantity;

            $shopProduct->pivot->save();

            $sale->delete();

            (
                new AuditLog([
                    'user_id' => $request->user()->id,
                    'operation' => "Delete Sale",
                    'description' => "Deleted sale #'$sale->id'"
                ])
            )
            ->save();

            return [
                "message" => "Success!!"
            ];
        });
    });

    Route::prefix('audit_logs')->group(function () {
        Route::get('/', function () {
            Gate::authorize('admin');

            return AuditLog::query()
                        ->with('user:id,full_name,username')
                        ->get();
        });
    });
});
