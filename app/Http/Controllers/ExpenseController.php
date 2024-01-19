<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function index()
    {
        Gate::authorize('admin');

        return Expense::withTrashed()
                    ->with('user:id,full_name')
                    ->with('shop:id,name')
                    ->get();
    }
}
