<?php

namespace Tests\Feature;

use App\Core\Accounts\Account;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use App\Tenancy\Models\Branch;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenancyInfrastructureTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_resolves_an_active_account_that_belongs_to_the_authenticated_user(): void
    {
        $user = $this->user();
        $account = $this->account('LOVE', 'tenant_love');
        $this->assign($user, $account);

        $resolved = app(TenantResolver::class)->resolve($this->requestFor($user, $account->id));

        $this->assertTrue($account->is($resolved));
    }

    public function test_it_rejects_an_account_that_does_not_belong_to_the_authenticated_user(): void
    {
        $user = $this->user();
        $account = $this->account('Touché', 'tenant_touche');

        $this->expectException(AuthorizationException::class);

        app(TenantResolver::class)->resolve($this->requestFor($user, $account->id));
    }

    public function test_it_rejects_an_inactive_account(): void
    {
        $user = $this->user();
        $account = $this->account('LOVE', 'tenant_love', 'inactive');
        $this->assign($user, $account);

        $this->expectException(AuthorizationException::class);

        app(TenantResolver::class)->resolve($this->requestFor($user, $account->id));
    }

    public function test_it_configures_the_tenant_connection_with_the_account_database_name(): void
    {
        $account = $this->account('LOVE', 'podiqdte_todays_love');

        app(TenantConnectionManager::class)->configure($account);

        $this->assertSame('podiqdte_todays_love', config('database.connections.tenant.database'));
    }

    public function test_tenant_models_use_the_explicit_tenant_connection(): void
    {
        $this->assertSame('tenant', (new Branch)->getConnectionName());
    }

    public function test_switching_accounts_replaces_the_configured_tenant_database(): void
    {
        $first = $this->account('LOVE', 'tenant_love');
        $second = $this->account('Touché', 'tenant_touche');
        $connections = app(TenantConnectionManager::class);

        $connections->configure($first);
        $this->assertSame('tenant_love', config('database.connections.tenant.database'));

        $connections->configure($second);
        $this->assertSame('tenant_touche', config('database.connections.tenant.database'));
    }

    private function requestFor(User $user, int $accountId): Request
    {
        $request = Request::create('/dashboard');
        $session = app('session')->driver();
        $session->put('active_account_id', $accountId);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }

    private function user(): User
    {
        return User::query()->create([
            'name' => 'Danielle',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function account(string $name, string $databaseName, string $status = 'active'): Account
    {
        return Account::query()->create([
            'name' => $name,
            'ruc' => fake()->unique()->numerify('##########'),
            'database_name' => $databaseName,
            'status' => $status,
        ]);
    }

    private function assign(User $user, Account $account): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'admin'],
            ['name' => 'Administrator'],
        );

        $user->accounts()->attach($account->id, ['role_id' => $role->id]);
    }
}
