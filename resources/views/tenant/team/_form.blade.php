@php
    $profile = $staffProfile ?? null;
    $member = $membership ?? null;
    $selectedRole = old('role_id', $member?->role_id);
    $selectedBranch = old('branch_id', $profile?->branch_id);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="team-name">Nombre para inicio de sesión</label>
        <input id="team-name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $member?->user?->name) }}" required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="team-email">Correo electrónico</label>
        <input id="team-email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $profile?->email ?? $member?->user?->email) }}" @readonly($profile) required>
        <div class="form-text">
            {{ $profile ? 'El correo global se administra desde Core Admin.' : 'Si ya existe, se reutilizará el usuario global.' }}
        </div>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @if (! $profile)
        <div class="col-md-6">
            <label class="form-label" for="team-password">Contraseña inicial</label>
            <input id="team-password" type="password" class="form-control @error('password') is-invalid @enderror" name="password">
            <div class="form-text">Obligatoria solo si el correo es nuevo.</div>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label" for="team-role">Rol operativo</label>
        <select id="team-role" class="form-select @error('role_id') is-invalid @enderror" name="role_id" required>
            <option value="">Selecciona un rol</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $selectedRole === (string) $role->id)>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
        @error('role_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="team-branch">Sucursal principal</label>
        <select id="team-branch" class="form-select @error('branch_id') is-invalid @enderror" name="branch_id">
            <option value="">Sin sucursal (solo Management)</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Obligatoria para Administrador de tienda y Asesora.</div>
        @error('branch_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="team-status">Estado</label>
        <select id="team-status" class="form-select" name="status">
            <option value="active" @selected(old('status', $profile?->status ?? 'active') === 'active')>Activo</option>
            <option value="inactive" @selected(old('status', $profile?->status) === 'inactive')>Inactivo</option>
        </select>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input id="team-can-work-other-branches" class="form-check-input" type="checkbox" name="can_work_other_branches" value="1" @checked(old('can_work_other_branches', $profile?->can_work_other_branches))>
            <label class="form-check-label" for="team-can-work-other-branches">
                Permitir asignación a otras sucursales
            </label>
        </div>
        <div class="form-text">
            Permite que esta colaboradora pueda ser asignada temporalmente en el horario de una sucursal distinta a su sucursal principal.
        </div>
    </div>
</div>

<hr class="my-4">

<h5>Datos personales y contacto</h5>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <label class="form-label" for="team-first-name">Nombres</label>
        <input id="team-first-name" class="form-control" name="first_name" value="{{ old('first_name', $profile?->first_name) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="team-last-name">Apellidos</label>
        <input id="team-last-name" class="form-control" name="last_name" value="{{ old('last_name', $profile?->last_name) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="team-birth-date">Fecha de nacimiento</label>
        <input id="team-birth-date" type="date" class="form-control" name="birth_date" value="{{ old('birth_date', $profile?->birth_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="team-hire-date">Fecha de ingreso</label>
        <input id="team-hire-date" type="date" class="form-control" name="hire_date" value="{{ old('hire_date', $profile?->hire_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="team-termination-date">Fecha de egreso</label>
        <input id="team-termination-date" type="date" class="form-control" name="termination_date" value="{{ old('termination_date', $profile?->termination_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="team-phone">Teléfono</label>
        <input id="team-phone" class="form-control" name="phone" value="{{ old('phone', $profile?->phone) }}">
    </div>
</div>

<hr class="my-4">

<h5>Contacto de emergencia</h5>

<div class="row g-3 mt-1">
    <div class="col-md-4">
        <label class="form-label" for="team-emergency-name">Nombre</label>
        <input id="team-emergency-name" class="form-control" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="team-emergency-phone">Teléfono</label>
        <input id="team-emergency-phone" class="form-control" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile?->emergency_contact_phone) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="team-emergency-relationship">Relación</label>
        <input id="team-emergency-relationship" class="form-control" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $profile?->emergency_contact_relationship) }}">
    </div>
</div>
