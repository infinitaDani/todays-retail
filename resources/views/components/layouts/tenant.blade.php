@props(['title' => null, 'subtitle' => null])

@php
    $activeAccount = request()->attributes->get('tenantAccount') ?? ($account ?? null);
    $tenantPermissions = $activeAccount && auth()->check()
        ? app(\App\Tenancy\TenantAccountAccess::class)->navigation(auth()->user(), $activeAccount)
        : [
            'role' => null,
            'account_administrator' => false,
            'can_manage' => false,
            'can_administer_schedule' => false,
            'can_operate' => false,
        ];
    $tenantRole = $tenantPermissions['role'];
    $canManageTenant = $tenantPermissions['can_manage'];
    $canAdministerSchedule = $tenantPermissions['can_administer_schedule'];
    $canOperateTenant = $tenantPermissions['can_operate'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title . ' · Today\'s Retail' : "Today's Retail" }}</title>
        @vite(['resources/scss/todays-retail.scss', 'resources/js/todays-retail.js'])
    </head>

    <body>
        <div class="app-shell">
            <header class="app-topbar">
                <a class="brand-link" href="{{ route('dashboard') }}">
                    <span class="brand-mark">TR</span>
                    <span>Today's Retail</span>
                </a>

                <button
                    class="topbar-button"
                    type="button"
                    data-sidenav-toggle
                    aria-label="Mostrar u ocultar menú"
                >
                    <i data-lucide="menu"></i>
                </button>

                <div class="topbar-account">
                    <span>Cuenta activa</span>
                    <strong>{{ $activeAccount?->name ?? 'Sin cuenta seleccionada' }}</strong>
                </div>

                <div class="topbar-actions">
                    <a
                        class="topbar-button"
                        href="{{ route('accounts.select') }}"
                        aria-label="Cambiar cuenta"
                    >
                        <i data-lucide="repeat-2"></i>
                    </a>

                    <button
                        class="topbar-button"
                        type="button"
                        data-theme-toggle
                        aria-label="Cambiar tema"
                    >
                        <i data-lucide="moon"></i>
                    </button>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button class="topbar-button" type="submit" aria-label="Cerrar sesión">
                            <i data-lucide="log-out"></i>
                        </button>
                    </form>
                </div>
            </header>

            <aside class="app-sidebar">
                <div class="sidebar-scroll" data-simplebar>
                    <p class="sidebar-heading">Principal</p>
                    <ul class="side-nav">
                        <li class="side-nav-item">
                            <a
                                class="side-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                                href="{{ route('dashboard') }}"
                            >
                                <i data-lucide="layout-dashboard"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    </ul>

                    @if ($canManageTenant)
                        <p class="sidebar-heading">Equipo</p>
                        <ul class="side-nav">
                            <li class="side-nav-item">
                                <a
                                    class="side-nav-link {{ request()->routeIs('team.*') ? 'active' : '' }}"
                                    href="{{ route('team.index') }}"
                                >
                                    <i data-lucide="users"></i>
                                    <span>Colaboradores</span>
                                </a>
                            </li>
                        </ul>
                    @endif

                    <p class="sidebar-heading">Operations</p>
                    @if ($canManageTenant)
                        <ul class="side-nav">
                            <li class="side-nav-item">
                                <a
                                    class="side-nav-link {{ request()->routeIs('operations.branches') ? 'active' : '' }}"
                                    href="{{ route('operations.branches') }}"
                                >
                                    <i data-lucide="store"></i>
                                    <span>Sucursales</span>
                                </a>
                            </li>
                            <li class="side-nav-item">
                                <a
                                    class="side-nav-link {{ request()->routeIs('operations.shifts') ? 'active' : '' }}"
                                    href="{{ route('operations.shifts') }}"
                                >
                                    <i data-lucide="clock-3"></i>
                                    <span>Turnos</span>
                                </a>
                            </li>
                        </ul>
                    @endif

                    @if ($canOperateTenant)
                        <ul class="side-nav">
                            <li class="side-nav-item">
                                <span class="side-nav-link">
                                    <i data-lucide="calendar-days"></i>
                                    <span>Horarios</span>
                                </span>
                                <ul class="side-nav-sub">
                                    @if ($canAdministerSchedule)
                                        <li>
                                            <a class="side-nav-link {{ request()->routeIs('operations.planner') ? 'active' : '' }}" href="{{ route('operations.planner') }}">
                                                <span>Planificar</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="side-nav-link {{ request()->routeIs('operations.schedule') ? 'active' : '' }}" href="{{ route('operations.schedule') }}">
                                                <span>Calendario</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="side-nav-link {{ request()->routeIs('operations.shifts') ? 'active' : '' }}" href="{{ route('operations.shifts') }}">
                                                <span>Turnos</span>
                                            </a>
                                        </li>
                                        @if ($canManageTenant)
                                            <li>
                                                <a
                                                    class="side-nav-link {{ request()->routeIs('operations.schedule.settings*') ? 'active' : '' }}"
                                                    href="{{ route('operations.schedule.settings') }}"
                                                >
                                                    <span>Configuración</span>
                                                </a>
                                            </li>
                                        @endif
                                        <li>
                                            <a
                                                class="side-nav-link {{ request()->routeIs('operations.schedule.report') ? 'active' : '' }}"
                                                href="{{ route('operations.schedule.report') }}"
                                            >
                                                <span>Reporte de jornada</span>
                                            </a>
                                        </li>
                                    @endif
                                    <li>
                                        <a class="side-nav-link {{ request()->routeIs('operations.my-tasks') ? 'active' : '' }}" href="{{ route('operations.my-tasks') }}">
                                            <span>Mis tareas</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>

                        <p class="sidebar-heading">Solicitudes</p>
                        <ul class="side-nav">
                            <li class="side-nav-item">
                                <a class="side-nav-link {{ request()->routeIs('requests.*') ? 'active' : '' }}" href="{{ route('requests.index') }}">
                                    <i data-lucide="file-text"></i>
                                    <span>Solicitudes</span>
                                </a>
                            </li>
                        </ul>
                    @endif

                    @if ($canManageTenant)
                        <p class="sidebar-heading">Tasks</p>
                        <ul class="side-nav">
                            <li class="side-nav-item">
                                <a class="side-nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('tasks.index') }}">
                                    <i data-lucide="list-todo"></i>
                                    <span>Tareas</span>
                                </a>
                            </li>
                            <li class="side-nav-item">
                                <a class="side-nav-link {{ request()->routeIs('checklists.*') ? 'active' : '' }}" href="{{ route('checklists.index') }}">
                                    <i data-lucide="clipboard-check"></i>
                                    <span>Checklists</span>
                                </a>
                            </li>
                        </ul>
                    @endif

                    @if ($canOperateTenant)
                        <p class="sidebar-heading">Knowledge</p>
                        <ul class="side-nav">
                            <li class="side-nav-item">
                                <a class="side-nav-link {{ request()->routeIs('knowledge.center', 'knowledge.read') ? 'active' : '' }}" href="{{ route('knowledge.center') }}">
                                    <i data-lucide="book-open"></i>
                                    <span>Knowledge Center</span>
                                </a>
                            </li>
                            @if ($canManageTenant)
                                <li class="side-nav-item">
                                    <a
                                        class="side-nav-link {{ request()->routeIs('knowledge.articles*', 'knowledge.categories*') ? 'active' : '' }}"
                                        href="{{ route('knowledge.articles') }}"
                                    >
                                        <i data-lucide="settings-2"></i>
                                        <span>Administrar Knowledge</span>
                                    </a>
                                </li>
                            @endif
                        </ul>

                        <p class="sidebar-heading">Productos</p>
                        <ul class="side-nav">
                            @if ($canManageTenant)
                                <li class="side-nav-item">
                                    <a
                                        class="side-nav-link {{ request()->routeIs('products.index', 'products.show', 'products.create', 'products.edit') ? 'active' : '' }}"
                                        href="{{ route('products.index') }}"
                                    >
                                        <i data-lucide="package"></i>
                                        <span>Catálogo</span>
                                    </a>
                                </li>
                                <li class="side-nav-item">
                                    <a class="side-nav-link {{ request()->routeIs('products.imports.*') ? 'active' : '' }}" href="{{ route('products.imports.create') }}">
                                        <i data-lucide="upload"></i>
                                        <span>Importar productos</span>
                                    </a>
                                </li>
                            @endif
                            <li class="side-nav-item">
                                <a class="side-nav-link {{ request()->routeIs('products.categories*') ? 'active' : '' }}" href="{{ route('products.categories') }}">
                                    <i data-lucide="tags"></i>
                                    <span>Categorías</span>
                                </a>
                            </li>
                            @if (\App\Modules\Products\Models\ProductSetting::first()?->manages_collections)
                                <li class="side-nav-item">
                                    <a class="side-nav-link {{ request()->routeIs('products.collections*') ? 'active' : '' }}" href="{{ route('products.collections') }}">
                                        <i data-lucide="layers-3"></i>
                                        <span>Colecciones</span>
                                    </a>
                                </li>
                            @endif
                            @if ($canManageTenant)
                                <li class="side-nav-item">
                                    <a class="side-nav-link {{ request()->routeIs('products.types*') ? 'active' : '' }}" href="{{ route('products.types.index') }}">
                                        <i data-lucide="shapes"></i>
                                        <span>Tipos</span>
                                    </a>
                                </li>
                                <li class="side-nav-item">
                                    <a class="side-nav-link {{ request()->routeIs('products.settings*') ? 'active' : '' }}" href="{{ route('products.settings') }}">
                                        <i data-lucide="sliders-horizontal"></i>
                                        <span>Configuración</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    @endif
                </div>
            </aside>

            <main class="content-page">
                <div class="content-container">
                    @if ($title || $subtitle)
                        <div class="page-title-box">
                            <div>
                                <h1>{{ $title }}</h1>
                                @if ($subtitle)
                                    <p>{{ $subtitle }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="tr-notice">{{ session('success') }}</div>
                    @endif

                    {{ $slot }}
                </div>

                <footer class="app-footer">© {{ now()->year }} Today's Retail</footer>
            </main>
        </div>

        @stack('page-scripts')
    </body>
</html>
