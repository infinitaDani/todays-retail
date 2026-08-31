<?php

namespace Tests\Unit;

use App\Core\Accounts\Account;
use App\Http\Controllers\MyTasksController;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MyTasksScopeTest extends TestCase
{
    public function test_advisor_cannot_request_another_collaborator(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->resolve(['core_user_id' => 99], ['role' => TenantOperationalScope::ADVISOR, 'branch_id' => 3], 7);
    }

    public function test_advisor_is_forced_to_own_user_and_branch(): void
    {
        $this->assertSame([3, 7], $this->resolve([], ['role' => TenantOperationalScope::ADVISOR, 'branch_id' => 3], 7));
    }

    public function test_store_admin_cannot_request_another_branch(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->resolve(['branch_id' => 4], ['role' => TenantOperationalScope::STORE_ADMIN, 'branch_id' => 3], 7);
    }

    private function resolve(array $filters, array $scope, int $currentUserId): array
    {
        $method = new ReflectionMethod(MyTasksController::class, 'resolveFilters');
        return $method->invoke(new MyTasksController, $filters, $scope, new Account, $currentUserId);
    }
}
