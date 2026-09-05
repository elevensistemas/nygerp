<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class make_admin extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'admin@eleven.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Trinitotolueno2015'),
            ]
        );
    }
}
