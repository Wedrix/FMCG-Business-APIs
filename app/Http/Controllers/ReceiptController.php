<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    public function index()
    {
        Gate::authorize('admin');

        return Receipt::query()
                    ->with('sales', function ($query) {
                        $query->withTrashed()
                            ->with('shop:id,name')
                            ->with('product:id,name,price')
                            ->with('user:id,full_name,username');
                    })
                    ->get();
    }
}
