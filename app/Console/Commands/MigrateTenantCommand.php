<?php

namespace App\Console\Commands;

use App\Core\Accounts\Account;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Console\Command;

class MigrateTenantCommand extends Command
{
    protected $signature = 'tenant:migrate
                            {account : Core account ID whose tenant database will be migrated}
                            {--force : Run in production without an interactive confirmation}';

    protected $description = 'Run tenant migrations for one active account only';

    public function handle(TenantConnectionManager $connections): int
    {
        $account = Account::query()->find($this->argument('account'));

        if (! $account) {
            $this->error('The Core account was not found.');

            return self::FAILURE;
        }

        if ($account->status !== 'active') {
            $this->error('Tenant migrations can only run for an active account.');

            return self::FAILURE;
        }

        $connections->configure($account);

        return $this->call('migrate', [
            '--database' => 'tenant',
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => $this->option('force'),
        ]);
    }
}
