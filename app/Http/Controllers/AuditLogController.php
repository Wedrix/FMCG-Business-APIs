<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index()
    {
        Gate::authorize('admin');

        return AuditLog::query()
                    ->with('user:id,full_name,username')
                    ->get();
    }
}
