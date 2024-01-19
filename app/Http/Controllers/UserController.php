<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use App\SMS\SMS;
use App\TextMessages\LoginCredentialsTextMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        Gate::authorize('super_admin');

        return User::with('shop:id,name')
                    ->get();
    }

    public function indexDeleted()
    {
        Gate::authorize('super_admin');

        return User::with('shop:id,name')
                    ->onlyTrashed()
                    ->get();
    }

    public function create(Request $request)
    {
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
    }

    public function restore(Request $request, string $userId)
    {
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
    }

    public function update(Request $request, User $user)
    {
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
    }

    public function delete(Request $request, User $user)
    {
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
    }
}
