<?php

namespace App\Tenancy;

use App\Core\Accounts\Account;
use App\Core\Accounts\AccountUser;
use App\Core\Users\User;

class TenantAccountAccess
{
    public const ACCOUNT_ADMIN_ROLE = 'admin';

    public function membership(User $user, Account $account): ?AccountUser
    {
        return $account->memberships()
            ->with('role')
            ->where('user_id', $user->id)
            ->first();
    }

    public function isAccountAdministrator(User $user, Account $account): bool
    {
        return $this->membership($user, $account)?->role?->code
            === self::ACCOUNT_ADMIN_ROLE;
    }

    public function canManageTenant(User $user, Account $account): bool
    {
        $role = $this->membership($user, $account)?->role?->code;

        return in_array(
            $role,
            [self::ACCOUNT_ADMIN_ROLE, TenantOperationalScope::MANAGEMENT],
            true,
        );
    }

    public function navigation(User $user, ?Account $account): array
    {
        if (! $account) {
            return [
                'role' => null,
                'account_administrator' => false,
                'can_manage' => false,
                'can_administer_schedule' => false,
                'can_sync_inventory' => false,
                'can_operate' => false,
            ];
        }
        $role = $this->membership($user, $account)?->role?->code;
        $accountAdministrator = $role === self::ACCOUNT_ADMIN_ROLE;

        return [
            'role' => $role,
            'account_administrator' => $accountAdministrator,
            'can_manage' => $accountAdministrator || $role === TenantOperationalScope::MANAGEMENT,
            'can_administer_schedule' => $accountAdministrator || in_array(
                $role,
                [
                    TenantOperationalScope::MANAGEMENT,
                    TenantOperationalScope::STORE_ADMIN,
                ],
                true,
            ),
            'can_sync_inventory' => $accountAdministrator || in_array(
                $role,
                [
                    TenantOperationalScope::MANAGEMENT,
                    TenantOperationalScope::STORE_ADMIN,
                ],
                true,
            ),
            'can_operate' => in_array(
                $role,
                [
                    self::ACCOUNT_ADMIN_ROLE,
                    TenantOperationalScope::MANAGEMENT,
                    TenantOperationalScope::STORE_ADMIN,
                    TenantOperationalScope::ADVISOR,
                ],
                true,
            ),
        ];
    }
}
