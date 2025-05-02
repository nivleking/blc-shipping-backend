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
        // THIS IS FOR LOCAL
        // COMMENT FOR PRODUCTION
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

        $user = new User();
        $user->name = 'user5';
        $user->email = 'user5@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        User::create([
            'name' => 'superadmin',
            'email' => 'superadmin@blc-shipping.com',
            'password' => bcrypt('password'),
            'password_plain' => 'password',
            'is_admin' => true,
            'is_super_admin' => true,
            'created_by' => 1,
            'updated_by' => 1,
            'status' => 'active',
        ]);

        $user = new User();
        $user->name = 'sidharta1';
        $user->email = 'sidharta1@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $user = new User();
        $user->name = 'sidharta';
        $user->email = 'sidharta@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $user = new User();
        $user->name = 'sidharta3';
        $user->email = 'sidharta3@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $user = new User();
        $user->name = 'sidharta4';
        $user->email = 'sidharta4@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        $user = new User();
        $user->name = 'sidharta5';
        $user->email = 'sidharta5@mail.com';
        $user->is_admin = false;
        $user->password = bcrypt('12');
        $user->save();

        User::create([
            'name' => 'megasuperadmin',
            'email' => 'megasuperadmin@blc-shipping.com',
            'password' => bcrypt('password'),
            'password_plain' => 'password',
            'is_admin' => true,
            'is_super_admin' => true,
            'created_by' => 1,
            'updated_by' => 1,
            'status' => 'active',
        ]);
    }
}
