<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index()
    {
        Gate::authorize('admin');

        return Product::all();
    }

    public function indexDeleted()
    {
        Gate::authorize('admin');

        return Product::onlyTrashed()->get();
    }

    public function create(Request $request)
    {
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
    }

    public function restore(Request $request, string $productId)
    {
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
    }

    public function update(Request $request, Product $product)
    {
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
    }

    public function delete(Request $request, Product $product)
    {
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
    }
}
