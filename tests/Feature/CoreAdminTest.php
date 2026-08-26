<?php

namespace Tests\Feature;

use App\Core\Accounts\Account;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoreAdminTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('core_admin.emails', ['core-admin@example.com']);
    }

    public function test_an_internal_administrator_can_create_an_account(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('admin.accounts.store'), $this->accountData())
            ->assertRedirect();

        $this->assertDatabaseHas('accounts', [
            'name' => 'LOVE',
            'ruc' => '0999999999001',
            'database_name' => 'tenant_0999999999001',
            'status' => 'active',
        ], 'core');
    }

    public function test_an_allowlisted_internal_administrator_can_log_in_without_an_account_membership(): void
    {
        $administrator = $this->administrator();

        $this->post(route('login.store'), [
            'email' => $administrator->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.accounts.index'));

        $this->assertAuthenticatedAs($administrator);
        $this->assertSessionMissing('active_account_id');
    }

    public function test_account_requires_unique_ruc_and_database_name(): void
    {
        Account::query()->create($this->accountData());

        $this->actingAs($this->administrator())
            ->from(route('admin.accounts.create'))
            ->post(route('admin.accounts.store'), $this->accountData())
            ->assertRedirect(route('admin.accounts.create'))
            ->assertSessionHasErrors(['ruc', 'database_name']);
    }

    public function test_an_internal_administrator_can_create_a_global_user_with_a_hashed_password(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('admin.users.store'), [
                'name' => 'María',
                'email' => 'maria@example.com',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'status' => 'active',
            ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertCount(0, $user->accounts()->get());
    }

    public function test_user_email_and_role_code_must_be_unique(): void
    {
        User::query()->create([
            'name' => 'Existing user',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        Role::query()->create(['name' => 'Administrator', 'code' => 'admin']);

        $this->actingAs($this->administrator())
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Duplicate user',
                'email' => 'existing@example.com',
                'password' => 'secure-password',
                'password_confirmation' => 'secure-password',
                'status' => 'active',
            ])->assertSessionHasErrors('email');

        $this->actingAs($this->administrator())
            ->from(route('admin.roles.create'))
            ->post(route('admin.roles.store'), ['name' => 'Another administrator', 'code' => 'admin'])
            ->assertSessionHasErrors('code');
    }

    public function test_an_administrator_can_create_a_role_and_assign_it_to_a_membership(): void
    {
        $administrator = $this->administrator();
        $account = Account::query()->create($this->accountData());
        $user = $this->user('danielle@example.com');

        $this->actingAs($administrator)
            ->post(route('admin.roles.store'), ['name' => 'Manager', 'code' => 'manager']);

        $role = Role::query()->where('code', 'manager')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('admin.accounts.memberships.store', $account), [
                'user_id' => $user->id,
                'role_id' => $role->id,
            ])->assertRedirect();

        $this->assertDatabaseHas('account_user', [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ], 'core');
    }

    public function test_a_user_can_belong_to_multiple_accounts_with_a_role_per_membership(): void
    {
        $administrator = $this->administrator();
        $user = $this->user('danielle@example.com');
        $first = Account::query()->create($this->accountData());
        $second = Account::query()->create([
            ...$this->accountData(),
            'name' => 'Tienda 2',
            'ruc' => '0999999999002',
            'database_name' => 'tenant_0999999999002',
        ]);
        $adminRole = Role::query()->create(['name' => 'Administrator', 'code' => 'admin']);
        $managerRole = Role::query()->create(['name' => 'Manager', 'code' => 'manager']);

        $this->actingAs($administrator)->post(route('admin.accounts.memberships.store', $first), ['user_id' => $user->id, 'role_id' => $adminRole->id]);
        $this->actingAs($administrator)->post(route('admin.accounts.memberships.store', $second), ['user_id' => $user->id, 'role_id' => $managerRole->id]);

        $this->assertCount(2, $user->accounts()->get());
        $this->assertSame($adminRole->id, $user->memberships()->where('account_id', $first->id)->firstOrFail()->role_id);
        $this->assertSame($managerRole->id, $user->memberships()->where('account_id', $second->id)->firstOrFail()->role_id);
    }

    public function test_a_membership_cannot_be_created_twice_for_the_same_user_and_account(): void
    {
        $administrator = $this->administrator();
        $account = Account::query()->create($this->accountData());
        $user = $this->user('danielle@example.com');
        $role = Role::query()->create(['name' => 'Administrator', 'code' => 'admin']);
        $account->users()->attach($user->id, ['role_id' => $role->id]);

        $this->actingAs($administrator)
            ->from(route('admin.accounts.show', $account))
            ->post(route('admin.accounts.memberships.store', $account), [
                'user_id' => $user->id,
                'role_id' => $role->id,
            ])->assertSessionHasErrors('user_id');
    }

    private function administrator(): User
    {
        return $this->user('core-admin@example.com');
    }

    private function user(string $email): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Core administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );
    }

    private function accountData(): array
    {
        return [
            'name' => 'LOVE',
            'ruc' => '0999999999001',
            'database_name' => 'tenant_0999999999001',
            'status' => 'active',
        ];
    }
}
