<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'GroPro') }}</title>
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gp-primary: #1e3a5f;
            --gp-primary-light: #2a5298;
            --gp-accent: #3b82f6;
            --gp-accent-hover: #2563eb;
            --gp-success: #10b981;
            --gp-warning: #f59e0b;
            --gp-danger: #ef4444;
            --gp-info: #06b6d4;
            --gp-bg: #f0f2f5;
            --gp-card-bg: #ffffff;
            --gp-text: #1f2937;
            --gp-text-muted: #6b7280;
            --gp-border: #e5e7eb;
            --gp-radius: 10px;
            --gp-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --gp-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
            --gp-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
        }

        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

        body {
            background-color: var(--gp-bg);
            color: var(--gp-text);
            padding-bottom: 60px;
        }

        /* ── Navbar ─────────────────────── */
        .gp-navbar {
            background: linear-gradient(135deg, var(--gp-primary) 0%, var(--gp-primary-light) 100%);
            box-shadow: var(--gp-shadow-md);
            padding: .75rem 0;
        }
        .gp-navbar .navbar-brand {
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: -.5px;
        }
        .gp-navbar .nav-link {
            font-weight: 500;
            padding: .5rem 1rem !important;
            border-radius: 8px;
            transition: background .2s;
        }
        .gp-navbar .nav-link:hover,
        .gp-navbar .nav-link.active {
            background: rgba(255,255,255,0.15);
        }

        /* ── Cards ──────────────────────── */
        .card {
            border: 1px solid var(--gp-border);
            border-radius: var(--gp-radius);
            box-shadow: var(--gp-shadow);
            background: var(--gp-card-bg);
            margin-bottom: 1.25rem;
            transition: box-shadow .2s;
        }
        .card:hover { box-shadow: var(--gp-shadow-md); }
        .card-header {
            background: var(--gp-card-bg);
            border-bottom: 1px solid var(--gp-border);
            border-radius: var(--gp-radius) var(--gp-radius) 0 0 !important;
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        /* ── Tables ─────────────────────── */
        .table { margin-bottom: 0; }
        .table thead th {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--gp-text-muted);
            font-weight: 600;
            border-bottom-width: 1px;
            padding: .85rem 1rem;
            background: #f9fafb;
        }
        .table tbody td {
            padding: .85rem 1rem;
            vertical-align: middle;
            border-color: var(--gp-border);
        }
        .table-hover tbody tr:hover { background-color: #f5f7ff; }

        /* ── Badges ─────────────────────── */
        .badge { font-weight: 500; padding: .4em .75em; font-size: .8rem; border-radius: 6px; }
        .badge-draft { background: #dbeafe; color: #1d4ed8; }
        .badge-received { background: #e5e7eb; color: #374151; }
        .badge-processing { background: #fef3c7; color: #92400e; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-custom { background: #fef3c7; color: #92400e; }

        /* ── Buttons ────────────────────── */
        .btn { font-weight: 500; border-radius: 8px; transition: all .2s; }
        .btn-primary { background: var(--gp-accent); border-color: var(--gp-accent); }
        .btn-primary:hover { background: var(--gp-accent-hover); border-color: var(--gp-accent-hover); transform: translateY(-1px); box-shadow: var(--gp-shadow); }
        .btn-success { background: var(--gp-success); border-color: var(--gp-success); }
        .btn-success:hover { background: #059669; border-color: #059669; }
        .btn-danger { background: var(--gp-danger); border-color: var(--gp-danger); }
        .btn-danger:hover { background: #dc2626; border-color: #dc2626; }

        /* ── Forms / Inputs ─────────────── */
        .form-control, .form-select {
            border: 1px solid var(--gp-border);
            border-radius: 8px;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--gp-accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .touch-input { font-size: 1.1rem; padding: 12px 16px; }
        .touch-btn { padding: 12px 24px; font-size: 1.1rem; }

        /* ── Alerts ─────────────────────── */
        .alert {
            border: none;
            border-radius: var(--gp-radius);
            font-weight: 500;
            padding: .85rem 1.25rem;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid var(--gp-success); }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid var(--gp-danger); }

        /* ── Page headings ──────────────── */
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gp-primary);
        }
        .page-subtitle {
            font-size: .85rem;
            font-weight: 400;
            color: var(--gp-text-muted);
        }

        /* ── Utility ────────────────────── */
        .text-muted { color: var(--gp-text-muted) !important; }
        .border-left-accent { border-left: 3px solid var(--gp-accent); }
    </style>
    @yield('styles')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark gp-navbar mb-4">
        <div class="container-fluid px-3 px-lg-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                {{ config('app.name', 'GroPro') }}
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                @auth
                <ul class="navbar-nav me-auto ms-lg-3 gap-1">
                    @if(auth()->user()->role === 'admin')
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Tableau de bord</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.catalog.*') ? 'active' : '' }}" href="{{ route('admin.catalog.index') }}">Catalogue</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.workers.*') ? 'active' : '' }}" href="{{ route('admin.workers.index') }}">Employés</a></li>
                    @else
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('worker.dashboard') ? 'active' : '' }}" href="{{ route('worker.dashboard') }}">Mes Commandes</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('worker.order.*') ? 'active' : '' }}" href="{{ route('worker.order.create') }}">Nouvelle Commande</a></li>
                    @endif
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-25" style="width:32px;height:32px;font-size:.85rem;font-weight:600;">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li class="px-3 py-2 text-muted" style="font-size:.8rem;">{{ auth()->user()->role === 'admin' ? 'Administrateur' : 'Employé' }}</li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item" type="submit">Déconnexion</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
                @endauth
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-lg-4">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <svg class="me-2 flex-shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <svg class="me-2 flex-shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </div>

    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    @yield('scripts')
</body>
</html>
