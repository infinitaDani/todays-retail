<?php

namespace App\Http\Controllers;

use App\Modules\Operations\Models\StaffProfile;
use App\Modules\Products\Models\Product;
use App\Modules\Requests\Models\TenantRequest;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantRequestController extends Controller
{
    public function index(Request $request, TenantOperationalScope $scopes): View
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $query = TenantRequest::query()->with('items.product')->latest();

        if (! $this->canManage($scope)) {
            $query->where('core_user_id', $request->user()->id);
        } elseif ($scope['role'] === TenantOperationalScope::STORE_ADMIN) {
            $query->whereIn('core_user_id', $this->branchUserIds($scope['branch_id']));
        }

        return view('tenant.requests.index', ['requests' => $query->paginate(15), 'scope' => $scope]);
    }

    public function create(Request $request, TenantOperationalScope $scopes): View
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));

        return view('tenant.requests.form', [
            'scope' => $scope,
            'users' => $this->availableUsers($request, $scope),
            'supplies' => $this->supplies(),
        ]);
    }

    public function store(Request $request, TenantOperationalScope $scopes): RedirectResponse
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $data = $this->validated($request);
        $userId = (int) ($data['core_user_id'] ?? $request->user()->id);
        $this->authorizeTarget($request, $scope, $userId);

        if ($data['type'] === 'supply') {
            return $this->storeSupply($request, $data, $userId);
        }

        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $data['ends_at'] ?? null;
        TenantRequest::create(array_merge($data, [
            'core_user_id' => $userId,
            'status' => 'pending',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]));

        return redirect()->route('requests.index')->with('success', 'Solicitud registrada.');
    }

    public function show(Request $request, TenantRequest $tenantRequest, TenantOperationalScope $scopes): View
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        $this->authorizeRequest($request, $scope, $tenantRequest);
        $tenantRequest->load('items.product');

        return view('tenant.requests.show', compact('tenantRequest', 'scope'));
    }

    public function review(Request $request, TenantRequest $tenantRequest, TenantOperationalScope $scopes): RedirectResponse
    {
        $scope = $scopes->for($request->user(), $request->attributes->get('tenantAccount'));
        if (! $this->canManage($scope)) {
            throw new AuthorizationException('No puedes gestionar solicitudes.');
        }
        $this->authorizeRequest($request, $scope, $tenantRequest);
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $tenantRequest->update(array_merge($data, [
            'reviewed_by_core_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]));

        return back()->with('success', 'Solicitud actualizada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(TenantRequest::TYPES)],
            'core_user_id' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:200'],
            'modality' => ['nullable', 'string', 'max:80'],
            'recovery_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24'],
            'month_key' => ['nullable', 'date_format:Y-m'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['integer', 'exists:tenant.products,id'],
            'items.*.quantity' => ['numeric', 'min:0.001'],
        ]);
    }

    private function storeSupply(Request $request, array $data, int $userId): RedirectResponse
    {
        $items = collect($data['items'] ?? [])->filter(fn (array $item) => ! empty($item['product_id']) && ! empty($item['quantity']));
        if ($items->isEmpty()) {
            return back()->withErrors(['items' => 'Selecciona al menos un suministro.'])->withInput();
        }
        $allowed = $this->supplies()->pluck('id');
        if ($items->pluck('product_id')->diff($allowed)->isNotEmpty()) {
            throw new AuthorizationException('Solo puedes solicitar suministros activos.');
        }
        DB::connection('tenant')->transaction(function () use ($data, $items, $userId): void {
            $request = TenantRequest::create([
                'core_user_id' => $userId,
                'type' => 'supply',
                'status' => 'pending',
                'month_key' => $data['month_key'] ?? now()->format('Y-m'),
                'comment' => $data['comment'] ?? null,
            ]);
            $request->items()->createMany($items->values()->all());
        });

        return redirect()->route('requests.index')->with('success', 'Solicitud mensual de suministros registrada.');
    }

    private function supplies()
    {
        return Product::query()->where('is_active', true)->whereHas('type', fn ($query) => $query->where('normalized_name', 'suministro'))->orderBy('name')->get();
    }

    private function canManage(array $scope): bool
    {
        return ($scope['is_account_administrator'] ?? false) || in_array($scope['role'], [TenantOperationalScope::MANAGEMENT, TenantOperationalScope::STORE_ADMIN], true);
    }

    private function availableUsers(Request $request, array $scope)
    {
        if (! $this->canManage($scope)) {
            return collect([$request->user()]);
        }
        $users = $request->attributes->get('tenantAccount')->users()->where('users.status', 'active')->orderBy('users.name')->get();
        return $scope['role'] === TenantOperationalScope::STORE_ADMIN ? $users->whereIn('id', $this->branchUserIds($scope['branch_id'])) : $users;
    }

    private function branchUserIds(int $branchId)
    {
        return StaffProfile::query()->where('branch_id', $branchId)->where('status', 'active')->pluck('core_user_id');
    }

    private function authorizeTarget(Request $request, array $scope, int $userId): void
    {
        if (! $this->availableUsers($request, $scope)->pluck('id')->contains($userId)) {
            throw new AuthorizationException('No puedes crear solicitudes para este colaborador.');
        }
    }

    private function authorizeRequest(Request $request, array $scope, TenantRequest $tenantRequest): void
    {
        if ($tenantRequest->core_user_id === $request->user()->id) {
            return;
        }
        if (! $this->canManage($scope) || ($scope['role'] === TenantOperationalScope::STORE_ADMIN && ! $this->branchUserIds($scope['branch_id'])->contains($tenantRequest->core_user_id))) {
            throw new AuthorizationException('No puedes acceder a esta solicitud.');
        }
    }
}
