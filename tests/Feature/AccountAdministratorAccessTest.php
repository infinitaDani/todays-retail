<?php

namespace Tests\Feature;

use App\Core\Accounts\Account;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use App\Http\Middleware\EnsureTenantManagement;
use App\Tenancy\TenantAccountAccess;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountAdministratorAccessTest extends TestCase
{
    use DatabaseMigrations;

    public function test_account_administrator_can_manage_only_the_account_of_its_membership(): void
    {
        $user = $this->user();
        $first = $this->account('Cuenta A', 'tenant_a');
        $second = $this->account('Cuenta B', 'tenant_b');
        $admin = Role::query()->create(['name' => 'Administrator', 'code' => 'admin']);
        $user->accounts()->attach($first->id, ['role_id' => $admin->id]);

        $access = app(TenantAccountAccess::class);
        $this->assertTrue($access->isAccountAdministrator($user, $first));
        $this->assertTrue($access->canManageTenant($user, $first));
        $this->assertFalse($access->isAccountAdministrator($user, $second));
        $this->assertFalse($access->canManageTenant($user, $second));
    }

    public function test_account_administrator_passes_management_middleware_for_its_active_account(): void
    {
        $user = $this->user(); $account = $this->account('Cuenta', 'tenant_middleware');
        $role = Role::query()->create(['name' => 'Administrator', 'code' => 'admin']);
        $user->accounts()->attach($account->id, ['role_id' => $role->id]);
        $request = Request::create('/tasks/tasks');
        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('tenantAccount', $account);

        $response = app(EnsureTenantManagement::class)->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($request->attributes->get('tenantOperationalScope')['is_account_administrator']);
    }

    public function test_management_keeps_management_permissions_without_becoming_account_administrator(): void
    {
        $user = $this->user(); $account = $this->account('Cuenta', 'tenant_c');
        $role = Role::query()->create(['name' => 'Management', 'code' => 'management']);
        $user->accounts()->attach($account->id, ['role_id' => $role->id]);

        $scope = app(TenantOperationalScope::class)->for($user, $account);
        $this->assertTrue(app(TenantOperationalScope::class)->canManageTenant($scope));
        $this->assertFalse($scope['is_account_administrator']);
    }

    public function test_store_admin_and_advisor_do_not_receive_management_permissions(): void
    {
        $scopes = app(TenantOperationalScope::class);
        $this->assertFalse($scopes->canManageTenant(['role' => TenantOperationalScope::STORE_ADMIN, 'is_account_administrator' => false]));
        $this->assertFalse($scopes->canManageTenant(['role' => TenantOperationalScope::ADVISOR, 'is_account_administrator' => false]));
        $this->assertTrue($scopes->canAdministerSchedule(['role' => TenantOperationalScope::STORE_ADMIN, 'is_account_administrator' => false]));
        $this->assertFalse($scopes->canAdministerSchedule(['role' => TenantOperationalScope::ADVISOR, 'is_account_administrator' => false]));
    }

    public function test_account_administrator_scope_requires_no_operational_profile(): void
    {
        $user = $this->user(); $account = $this->account('Cuenta', 'tenant_d');
        $role = Role::query()->create(['name' => 'Administrator', 'code' => 'admin']);
        $user->accounts()->attach($account->id, ['role_id' => $role->id]);

        $scope = app(TenantOperationalScope::class)->for($user, $account);
        $this->assertTrue($scope['is_account_administrator']);
        $this->assertNull($scope['branch_id']);
    }

    private function user(): User { return User::query()->create(['name' => 'Admin', 'email' => fake()->unique()->safeEmail(), 'password' => Hash::make('password'), 'status' => 'active']); }
    private function account(string $name, string $database): Account { return Account::query()->create(['name' => $name, 'ruc' => fake()->unique()->numerify('##########'), 'database_name' => $database, 'status' => 'active']); }
}
