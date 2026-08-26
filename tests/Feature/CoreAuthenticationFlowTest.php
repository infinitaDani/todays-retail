<?php

namespace Tests\Feature;

use App\Core\Accounts\Account;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoreAuthenticationFlowTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_user_can_belong_to_multiple_accounts(): void
    {
        $user = $this->user();
        $first = $this->account('LOVE');
        $second = $this->account('Tienda 2');

        $this->assign($user, $first, 'admin');
        $this->assign($user, $second, 'manager');

        $this->assertCount(2, $user->accounts()->get());
    }

    public function test_a_user_cannot_select_an_account_that_does_not_belong_to_them(): void
    {
        $user = $this->user();
        $authorizedAccount = $this->account('LOVE');
        $otherAccount = $this->account('Tienda 2');
        $this->assign($user, $authorizedAccount, 'admin');

        $this->actingAs($user)
            ->post(route('accounts.select.store'), ['account_id' => $otherAccount->id])
            ->assertForbidden()
            ->assertSessionMissing('active_account_id');
    }

    public function test_a_user_with_one_active_account_is_selected_automatically_after_login(): void
    {
        $user = $this->user();
        $account = $this->account('LOVE');
        $this->assign($user, $account, 'admin');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSessionHas('active_account_id', $account->id);
    }

    public function test_a_user_with_multiple_accounts_must_use_the_selector_after_login(): void
    {
        $user = $this->user();
        $this->assign($user, $this->account('LOVE'), 'admin');
        $this->assign($user, $this->account('Tienda 2'), 'manager');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('accounts.select'));

        $this->assertAuthenticatedAs($user);
        $this->assertSessionMissing('active_account_id');
    }

    public function test_a_user_can_change_between_their_authorized_accounts(): void
    {
        $user = $this->user();
        $first = $this->account('LOVE');
        $second = $this->account('Tienda 2');
        $this->assign($user, $first, 'admin');
        $this->assign($user, $second, 'manager');

        $this->actingAs($user)
            ->withSession(['active_account_id' => $first->id])
            ->post(route('accounts.select.store'), ['account_id' => $second->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('active_account_id', $second->id);
    }

    public function test_an_inactive_user_cannot_authenticate(): void
    {
        $user = $this->user(['status' => 'inactive']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function user(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Danielle',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => 'active',
        ], $attributes));
    }

    private function account(string $name): Account
    {
        return Account::query()->create([
            'name' => $name,
            'ruc' => fake()->unique()->numerify('##########'),
            'database_name' => 'tenant_'.fake()->unique()->numerify('##########'),
            'status' => 'active',
        ]);
    }

    private function assign(User $user, Account $account, string $roleCode): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $roleCode],
            ['name' => ucfirst($roleCode)],
        );

        $user->accounts()->attach($account->id, ['role_id' => $role->id]);
    }
}
