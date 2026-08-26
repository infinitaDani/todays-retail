@props(['title' => null, 'subtitle' => null])
@php($activeAccount = request()->attributes->get('tenantAccount') ?? ($account ?? null))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title . ' · Today\'s Retail' : "Today's Retail" }}</title>
        @vite(['resources/scss/todays-retail.scss', 'resources/js/todays-retail.js'])
    </head>
    <body>
        <div class="app-shell">
            <header class="app-topbar">
                <a class="brand-link" href="{{ route('dashboard') }}"><span class="brand-mark">TR</span><span>Today's Retail</span></a>
                <button class="topbar-button" type="button" data-sidenav-toggle aria-label="Mostrar u ocultar menú"><i data-lucide="menu"></i></button>
                <div class="topbar-account"><span>Cuenta activa</span><strong>{{ $activeAccount?->name ?? 'Sin cuenta seleccionada' }}</strong></div>
                <div class="topbar-actions">
                    <a class="topbar-button" href="{{ route('accounts.select') }}" aria-label="Cambiar cuenta"><i data-lucide="repeat-2"></i></a>
                    <button class="topbar-button" type="button" data-theme-toggle aria-label="Cambiar tema"><i data-lucide="moon"></i></button>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="topbar-button" type="submit" aria-label="Cerrar sesión"><i data-lucide="log-out"></i></button></form>
                </div>
            </header>

            <aside class="app-sidebar">
                <div class="sidebar-scroll" data-simplebar>
                    <p class="sidebar-heading">Principal</p>
                    <ul class="side-nav"><li class="side-nav-item"><a class="side-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i data-lucide="layout-dashboard"></i><span>Dashboard</span></a></li></ul>
                    <p class="sidebar-heading">Equipo</p>
                    <ul class="side-nav"><li class="side-nav-item"><a class="side-nav-link" href="#" aria-disabled="true"><i data-lucide="users"></i><span>Colaboradores</span></a></li></ul>
                    <p class="sidebar-heading">Operations</p>
                    <ul class="side-nav">
                        <li class="side-nav-item"><a class="side-nav-link {{ request()->routeIs('operations.branches') ? 'active' : '' }}" href="{{ route('operations.branches') }}"><i data-lucide="store"></i><span>Sucursales</span></a></li>
                        <li class="side-nav-item"><a class="side-nav-link {{ request()->routeIs('operations.shifts') ? 'active' : '' }}" href="{{ route('operations.shifts') }}"><i data-lucide="clock-3"></i><span>Turnos</span></a></li>
                        <li class="side-nav-item"><a class="side-nav-link {{ request()->routeIs('operations.schedule') ? 'active' : '' }}" href="{{ route('operations.schedule') }}"><i data-lucide="calendar-days"></i><span>Horarios</span></a></li>
                    </ul>
                    <p class="sidebar-heading">Tasks</p>
                    <ul class="side-nav">
                        <li class="side-nav-item"><a class="side-nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('tasks.index') }}"><i data-lucide="list-todo"></i><span>Tareas</span></a></li>
                        <li class="side-nav-item"><a class="side-nav-link {{ request()->routeIs('checklists.*') ? 'active' : '' }}" href="{{ route('checklists.index') }}"><i data-lucide="clipboard-check"></i><span>Checklists</span></a></li>
                    </ul>
                    <p class="sidebar-heading">Knowledge</p>
                    <ul class="side-nav"><li class="side-nav-item"><a class="side-nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('knowledge.articles') }}"><i data-lucide="book-open"></i><span>Knowledge Center</span></a></li></ul>
                </div>
            </aside>

            <main class="content-page">
                <div class="content-container">
                    @if ($title || $subtitle)<div class="page-title-box"><div><h1>{{ $title }}</h1>@if ($subtitle)<p>{{ $subtitle }}</p>@endif</div></div>@endif
                    @if (session('success'))<div class="tr-notice">{{ session('success') }}</div>@endif
                    {{ $slot }}
                </div>
                <footer class="app-footer">© {{ now()->year }} Today's Retail</footer>
            </main>
        </div>
    </body>
</html>
