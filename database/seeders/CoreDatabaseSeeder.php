<?php

namespace Database\Seeders;

use App\Core\Accounts\Account;
use App\Core\Accounts\AccountUser;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['code' => 'admin'],
            ['name' => 'Administrator']
        );

        $account = Account::firstOrCreate(
            ['ruc' => '0000000000001'],
            [
                'name' => "Today's Retail Demo",
                'database_name' => 'tenant_demo',
                'status' => 'active',
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'admin@todays-retail.test'],
            [
                'name' => 'Danielle Admin',
                'password' => Hash::make('TodaysRetail2026!'),
                'status' => 'active',
            ]
        );

        AccountUser::firstOrCreate(
            [
                'account_id' => $account->id,
                'user_id' => $user->id,
            ],
            [
                'role_id' => $role->id,
            ]
        );
    }
}