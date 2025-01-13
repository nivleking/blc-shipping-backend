<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = new User();
        $user->name = 'user1';
        $user->email = 'user1@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $user = new User();
        $user->name = 'user2';
        $user->email = 'user2@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $user = new User();
        $user->name = 'user3';
        $user->email = 'user3@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $user = new User();
        $user->name = 'user4';
        $user->email = 'user4@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $admin = new User();
        $admin->name = 'admin';
        $admin->email = 'admin@mail.com';
        $admin->is_admin = true;
        $admin->password = bcrypt('12');
        $admin->save();
    }

}
