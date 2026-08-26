<?php

namespace App\Tenancy;

use App\Core\Accounts\Account;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class TenantConnectionManager
{
    public function configure(Account $account): Connection
    {
        $config = config('database.connections.tenant');
        $config['database'] = $account->database_name;

        config(['database.connections.tenant' => $config]);

        DB::purge('tenant');

        return DB::reconnect('tenant');
    }
}
