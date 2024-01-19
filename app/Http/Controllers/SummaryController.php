<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SummaryController extends Controller
{
    public function index()
    {
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
    }

    // Total Daily Sales For The Week
    public function graph1()
    {
        Gate::authorize('admin');

        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $dayOfWeek = now()->subDays($i)->locale('en')->minDayName;

            $data[$dayOfWeek] = Sale::query()
                                    ->whereDate('created_at', now()->subDays($i))
                                    ->sum('total');
        }

        return $data;
    }

    // Today's Shops' Total Sales
    public function graph2()
    {
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
    }

    // Today's Shops' Total Expenses
    public function graph3()
    {
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
    }

    // Today's Shops' Total Income
    public function graph4()
    {
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
    }

    public function indexAuditLogs()
    {
        Gate::authorize('admin');

        return AuditLog::query()
                    ->with('user:id,full_name,username')
                    ->latest()
                    ->take(12)
                    ->get();
    }
}
