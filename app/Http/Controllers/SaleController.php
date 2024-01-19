<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function index()
    {
        Gate::authorize('admin');

        return Sale::query()
                    ->with('shop:id,name')
                    ->with('product:id,name,price')
                    ->with('user:id,full_name,username')
                    ->get();
    }

    public function indexDeleted()
    {
        Gate::authorize('admin');

        return Sale::onlyTrashed()
                    ->with('shop:id,name')
                    ->with('product:id,name,price')
                    ->with('user:id,full_name,username')
                    ->get();
    }

    public function restore(Request $request, string $saleId)
    {
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
    }

    public function delete(Request $request, Sale $sale)
    {
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
    }
}
