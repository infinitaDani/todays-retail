<?php
namespace App\Tenancy;
use App\Core\Accounts\Account; use Illuminate\Auth\Access\AuthorizationException;
class AuthorizedCoreUser { public function ensure(Account $account, int $userId): void { if (!$account->users()->where('users.id',$userId)->exists()) throw new AuthorizationException('The Core user does not belong to the active account.'); } }
