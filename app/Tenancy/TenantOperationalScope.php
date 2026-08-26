<?php

namespace App\Tenancy;

use App\Core\Accounts\Account;
use App\Core\Accounts\AccountUser;
use App\Core\Users\User;
use App\Modules\Operations\Models\StaffProfile;
use Illuminate\Auth\Access\AuthorizationException;

class TenantOperationalScope
{
    public const MANAGEMENT = 'management';
    public const STORE_ADMIN = 'store_admin';
    public const ADVISOR = 'advisor';

    public function for(User $user, Account $account): array
    {
        /** @var AccountUser|null $membership */
        $membership = $account->memberships()->with('role')->where('user_id', $user->id)->first();

        if (! $membership || $user->status !== 'active') {
            throw new AuthorizationException('No tienes acceso a esta cuenta.');
        }

        $roleCode = $membership->role?->code;
        if (! in_array($roleCode, $this->allowedRoleCodes(), true)) {
            throw new AuthorizationException('Tu rol no tiene acceso a la operación tenant.');
        }

        if ($roleCode === self::MANAGEMENT) {
            return ['role' => $roleCode, 'branch_id' => null];
        }

        $profile = StaffProfile::query()->where('core_user_id', $user->id)->first();
        if (! $profile?->branch_id) {
            throw new AuthorizationException('Tu perfil operativo no tiene una sucursal asignada.');
        }

        return ['role' => $roleCode, 'branch_id' => (int) $profile->branch_id];
    }

    public function allowedRoleCodes(): array
    {
        return [self::MANAGEMENT, self::STORE_ADMIN, self::ADVISOR];
    }

    public function canAdministerSchedule(array $scope): bool
    {
        return in_array($scope['role'], [self::MANAGEMENT, self::STORE_ADMIN], true);
    }
}
