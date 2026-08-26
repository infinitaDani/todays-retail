<?php

namespace Tests\Unit;

use App\Core\Accounts\Account;
use App\Core\Accounts\AccountUser;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use PHPUnit\Framework\TestCase;

class CoreRelationshipsTest extends TestCase
{
    public function test_account_and_user_are_related_through_account_user(): void
    {
        $account = new Account;
        $user = new User;

        $this->assertSame('account_user', $account->users()->getTable());
        $this->assertSame('account_user', $user->accounts()->getTable());
        $this->assertSame(AccountUser::class, $account->users()->getPivotClass());
        $this->assertSame(AccountUser::class, $user->accounts()->getPivotClass());
    }

    public function test_role_belongs_to_the_account_user_membership(): void
    {
        $membership = new AccountUser;
        $role = new Role;

        $this->assertSame('roles', $membership->role()->getRelated()->getTable());
        $this->assertSame('account_user', $role->memberships()->getRelated()->getTable());
    }
}
