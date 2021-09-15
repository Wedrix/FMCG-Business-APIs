<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $user = new User;
        $user->username = 'wedrix';
        $user->full_name = 'Wedam Anewenah';
        $user->phone_number = '233509297419';
        $user->password = bcrypt('tester');
        $user->role = 'super_admin';

        $user->save();
    }
}
