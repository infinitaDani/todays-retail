<?php

namespace App\Http\Controllers;

use App\Core\Accounts\AccountUser;
use App\Core\Accounts\Role;
use App\Core\Users\User;
use App\Modules\Operations\Models\Branch;
use App\Modules\Operations\Models\StaffDocument;
use App\Modules\Operations\Models\StaffProfile;
use App\Tenancy\TenantOperationalScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantTeamController extends Controller
{
    public function index(Request $request, TenantOperationalScope $scopes): View
    {
        $account = $request->attributes->get('tenantAccount');
        $query = $account->memberships()->with(['user', 'role']);
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->whereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->filled('role')) {
            $query->whereHas('role', fn ($roles) => $roles->where('code', $request->string('role')->toString()));
        }
        if ($request->filled('branch_id')) {
            $query->whereIn('user_id', StaffProfile::query()->where('branch_id', $request->integer('branch_id'))->pluck('core_user_id'));
        }
        if ($request->filled('status')) {
            $query->whereIn('user_id', StaffProfile::query()->where('status', $request->string('status')->toString())->pluck('core_user_id'));
        }
        $memberships = $query->orderBy('id')->paginate(10)->withQueryString();
        $profiles = StaffProfile::query()->whereIn('core_user_id', $memberships->pluck('user_id'))->get()->keyBy('core_user_id');

        return view('tenant.team.index', [
            'memberships' => $memberships,
            'profiles' => $profiles,
            'roles' => Role::query()->whereIn('code', $scopes->allowedRoleCodes())->orderBy('name')->get(),
            'branches' => Branch::query()->where('status', 'active')->orderBy('name')->get(),
            'summary' => [
                'total' => $account->memberships()->count(),
                'active' => StaffProfile::query()->where('status', 'active')->count(),
                'management' => $account->memberships()->whereHas('role', fn ($roles) => $roles->where('code', 'management'))->count(),
                'store_admin' => $account->memberships()->whereHas('role', fn ($roles) => $roles->where('code', 'store_admin'))->count(),
                'advisor' => $account->memberships()->whereHas('role', fn ($roles) => $roles->where('code', 'advisor'))->count(),
            ],
        ]);
    }

    public function create(TenantOperationalScope $scopes): View
    {
        return view('tenant.team.create', $this->formData($scopes));
    }

    public function store(Request $request, TenantOperationalScope $scopes): RedirectResponse
    {
        $data = $this->validateProfile($request, true);
        $account = $request->attributes->get('tenantAccount');
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user && empty($data['password'])) {
            return back()->withErrors(['password' => 'La contraseña inicial es obligatoria para un correo nuevo.'])->withInput();
        }
        $role = $this->operationalRole($data['role_id'], $scopes);
        $this->validateBranchForRole($data['branch_id'] ?? null, $role->code);
        DB::connection('core')->transaction(function () use (&$user, $data, $account, $role): void {
            if (! $user) {
                $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'status' => 'active']);
            }
            $membership = AccountUser::query()->firstOrNew(['account_id' => $account->id, 'user_id' => $user->id]);
            $membership->role_id = $role->id;
            $membership->save();
        });
        $profile = StaffProfile::query()->updateOrCreate(['core_user_id' => $user->id], $this->profileAttributes($data));
        return redirect()->route('team.show', $profile)->with('success', 'Colaborador creado correctamente.');
    }

    public function show(Request $request, StaffProfile $staffProfile): View
    {
        return view('tenant.team.show', $this->profileData($request, $staffProfile));
    }

    public function edit(Request $request, StaffProfile $staffProfile, TenantOperationalScope $scopes): View
    {
        return view('tenant.team.edit', array_merge($this->formData($scopes), $this->profileData($request, $staffProfile)));
    }

    public function update(Request $request, StaffProfile $staffProfile, TenantOperationalScope $scopes): RedirectResponse
    {
        $membership = $this->membershipForProfile($request->attributes->get('tenantAccount')->id, $staffProfile);
        $data = $this->validateProfile($request);
        $role = $this->operationalRole($data['role_id'], $scopes);
        $this->validateBranchForRole($data['branch_id'] ?? null, $role->code);
        $membership->update(['role_id' => $role->id]);
        User::query()->whereKey($staffProfile->core_user_id)->update(['name' => $data['name']]);
        $staffProfile->update($this->profileAttributes($data));
        return redirect()->route('team.show', $staffProfile)->with('success', 'Colaborador actualizado correctamente.');
    }

    public function toggleStatus(Request $request, StaffProfile $staffProfile): RedirectResponse
    {
        $this->membershipForProfile($request->attributes->get('tenantAccount')->id, $staffProfile);
        $staffProfile->update(['status' => $staffProfile->status === 'active' ? 'inactive' : 'active']);
        return back()->with('success', 'Estado del colaborador actualizado.');
    }

    public function storeDocument(Request $request, StaffProfile $staffProfile): RedirectResponse
    {
        $account = $request->attributes->get('tenantAccount');
        $this->membershipForProfile($account->id, $staffProfile);
        $data = $request->validate([
            'type' => ['required', Rule::in(['cv', 'certificate', 'police_record'])],
            'title' => ['nullable', 'string', 'max:200', 'required_if:type,certificate'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'issued_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (in_array($data['type'], ['cv', 'police_record'], true)) {
            foreach ($staffProfile->documents()->where('type', $data['type'])->get() as $existing) {
                Storage::disk($existing->disk)->delete($existing->path);
                $existing->delete();
            }
        }
        $file = $data['file'];
        $path = $file->store("tenants/{$account->id}/staff/{$staffProfile->id}", 'local');
        $staffProfile->documents()->create(['type' => $data['type'], 'title' => $data['title'] ?? null, 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'issued_at' => $data['issued_at'] ?? null, 'expires_at' => $data['expires_at'] ?? null, 'notes' => $data['notes'] ?? null]);
        return back()->with('success', 'Documento guardado correctamente.');
    }

    public function downloadDocument(Request $request, StaffProfile $staffProfile, StaffDocument $document)
    {
        $this->membershipForProfile($request->attributes->get('tenantAccount')->id, $staffProfile);
        if ($document->staff_profile_id !== $staffProfile->id || ! Storage::disk($document->disk)->exists($document->path)) { abort(404); }
        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroyDocument(Request $request, StaffProfile $staffProfile, StaffDocument $document): RedirectResponse
    {
        $this->membershipForProfile($request->attributes->get('tenantAccount')->id, $staffProfile);
        if ($document->staff_profile_id !== $staffProfile->id || $document->type !== 'certificate') { abort(404); }
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
        return back()->with('success', 'Certificado eliminado.');
    }

    private function validateProfile(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'],
            'password' => [$creating ? 'nullable' : 'prohibited', 'string', 'min:8', 'max:255'],
            'role_id' => ['required', 'integer'], 'branch_id' => ['nullable', 'integer'],
            'first_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'], 'phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'], 'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function profileAttributes(array $data): array
    {
        return collect($data)->only(['branch_id', 'first_name', 'last_name', 'birth_date', 'phone', 'email', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'status'])->all();
    }

    private function operationalRole(int $roleId, TenantOperationalScope $scopes): Role
    {
        $role = Role::query()->whereKey($roleId)->whereIn('code', $scopes->allowedRoleCodes())->first();
        if (! $role) { abort(422, 'Solo puedes asignar roles operativos autorizados.'); }
        return $role;
    }

    private function validateBranchForRole(?int $branchId, string $roleCode): void
    {
        if ($branchId && ! Branch::query()->whereKey($branchId)->where('status', 'active')->exists()) { abort(422, 'La sucursal seleccionada no está disponible.'); }
        if (in_array($roleCode, [TenantOperationalScope::STORE_ADMIN, TenantOperationalScope::ADVISOR], true) && ! $branchId) { abort(422, 'Store Admin y Asesora requieren una sucursal.'); }
    }

    private function membershipForProfile(int $accountId, StaffProfile $staffProfile): AccountUser
    {
        $membership = AccountUser::query()->where('account_id', $accountId)->where('user_id', $staffProfile->core_user_id)->first();
        if (! $membership) { throw new AuthorizationException('Este colaborador no pertenece a la cuenta activa.'); }
        return $membership;
    }

    private function formData(TenantOperationalScope $scopes): array
    {
        return ['roles' => Role::query()->whereIn('code', $scopes->allowedRoleCodes())->orderBy('name')->get(), 'branches' => Branch::query()->where('status', 'active')->orderBy('name')->get()];
    }

    private function profileData(Request $request, StaffProfile $staffProfile): array
    {
        $membership = $this->membershipForProfile($request->attributes->get('tenantAccount')->id, $staffProfile);
        $staffProfile->load(['branch', 'documents']);
        return ['staffProfile' => $staffProfile, 'membership' => $membership->load(['user', 'role'])];
    }
}
