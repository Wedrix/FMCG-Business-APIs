<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Shop;
use App\SMS\SMS;
use App\TextMessages\NewSaleTextMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShopController extends Controller
{
    public function index()
    {
        Gate::authorize('admin');

        return Shop::withCount('products')->get();
    }

    public function indexDeleted()
    {
        Gate::authorize('admin');

        return Shop::onlyTrashed()->get();
    }

    public function create(Request $request)
    {
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
    }

    public function update(Request $request, Shop $shop)
    {
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
    }

    public function restore(Request $request, string $shopId)
    {
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
    }

    public function delete(Request $request, Shop $shop)
    {
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
    }

    public function indexSales(Request $request, Shop $shop)
    {
        Gate::authorize('sales_man', $shop->id);

        $beforeDate = now()->subDays(7);
        
        return $shop->sales()
                    ->with('shop:id,name')
                    ->with('product:id,name,price')
                    ->with('user:id,full_name,username')
                    ->where('created_at','>=',$beforeDate)
                    ->get();
    }

    public function indexDeletedSales(Request $request, Shop $shop)
    {
        Gate::authorize('sales_man', $shop->id);

        return $shop->sales()
                    ->onlyTrashed()
                    ->with('shop:id,name')
                    ->with('product:id,name,price')
                    ->with('user:id,full_name,username')
                    ->get();
    }

    public function createSale(Request $request, Shop $shop)
    {
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
    }

    public function deleteSale(Request $request, Shop $shop, string $saleId)
    {
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
    }

    public function restoreSale(Request $request, Shop $shop, string $saleId)
    {
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
    }

    public function indexProducts(Request $request, Shop $shop)
    {
        Gate::authorize('sales_man', $shop->id);

        return $shop->products;
    }

    public function indexExpenses(Request $request, Shop $shop)
    {
        Gate::authorize('sales_man', $shop->id);

        return $shop->expenses()
                    ->with('user:id,full_name')
                    ->with('shop:id,name')
                    ->get();
    }

    public function createExpense(Request $request, Shop $shop)
    {
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
    }

    public function deleteExpense(Request $request, Shop $shop, string $expenseId)
    {
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
    }

    public function createProduct(Request $request, Shop $shop)
    {
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

            if (is_null($product)) {
                return response()->json(
                    data: [
                        "message" => "The given data was invalid",
                        "error" => [
                            "products.$index.quantity" => "No product with id '$productId' exists."
                        ]
                    ],
                    status:422
                );
            }

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
    }

    public function deleteProduct(Request $request, Shop $shop)
    {
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
    }
}
