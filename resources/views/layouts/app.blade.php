<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Accounting Information System') }} - @yield('title', 'Financial Management')</title>

    <!-- Local Offline Assets: Bootstrap 5.3 & Icons (No Internet Connection Needed) -->
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">

    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #3b82f6;
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #ffffff;
            --topbar-height: 64px;
            --bg-body: #f8fafc;
        }

        body {
            background-color: var(--bg-body);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            color: #334155;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        #sidebar-wrapper {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            transition: margin 0.25s ease-out;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            background: #020617;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand a {
            color: #f8fafc;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .sidebar-section-title {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1.25rem 1.25rem 0.4rem;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0 0.75rem;
            margin: 0;
        }

        .sidebar-nav li {
            margin-bottom: 0.2rem;
        }

        .sidebar-nav .nav-link {
            color: var(--sidebar-text);
            padding: 0.6rem 0.85rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.88rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-nav .nav-link i {
            font-size: 1rem;
            width: 1.25rem;
            text-align: center;
        }

        .sidebar-nav .nav-link:hover {
            color: #ffffff;
            background-color: var(--sidebar-hover);
        }

        .sidebar-nav .nav-link.active {
            color: var(--sidebar-text-active);
            background-color: var(--sidebar-active);
            font-weight: 600;
        }

        /* Page Content Wrapper */
        #page-content-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Topbar */
        .topbar {
            height: var(--topbar-height);
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 990;
        }

        .main-content {
            padding: 1.75rem 1.5rem;
            flex: 1;
        }

        /* Card and Table utilities for Finance */
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            background: #ffffff;
        }

        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        .table-finance th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-finance td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
            font-size: 0.88rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .num-col {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 500;
        }

        .badge-status {
            padding: 0.35em 0.65em;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 2rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* Custom scrollbar for sidebar */
        #sidebar-wrapper::-webkit-scrollbar {
            width: 5px;
        }
        #sidebar-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        @media (max-width: 991.98px) {
            #sidebar-wrapper {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            #sidebar-wrapper.toggled {
                margin-left: 0;
            }
            #page-content-wrapper {
                margin-left: 0;
            }
        }
    </style>
    @yield('styles')
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar-wrapper">
            <div class="sidebar-brand">
                <a href="{{ route('dashboard') }}">
                    <i class="fa-solid fa-chart-pie text-primary"></i>
                    <span>Apex<span class="text-primary">AIS</span></span>
                </a>
            </div>

            <div class="sidebar-section-title">Overview</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-title">General Ledger</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="{{ route('accounts.index') }}" class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-sitemap"></i>
                        <span>Chart of Accounts</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('journal-entries.index') }}" class="nav-link {{ request()->routeIs('journal-entries.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-book-journal-whills"></i>
                        <span>Journal Entries</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.general-ledger') }}" class="nav-link {{ request()->routeIs('reports.general-ledger') ? 'active' : '' }}">
                        <i class="fa-solid fa-book"></i>
                        <span>General Ledger</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-title">Sales & Receivables</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="{{ route('contacts.index', ['type' => 'customer']) }}" class="nav-link {{ request()->is('contacts*') && request('type') == 'customer' ? 'active' : '' }}">
                        <i class="fa-solid fa-users"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('invoices.index') }}" class="nav-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        <span>Sales Invoices</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-title">Purchases & Payables</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="{{ route('contacts.index', ['type' => 'vendor']) }}" class="nav-link {{ request()->is('contacts*') && request('type') == 'vendor' ? 'active' : '' }}">
                        <i class="fa-solid fa-truck-field"></i>
                        <span>Vendors / Suppliers</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('bills.index') }}" class="nav-link {{ request()->routeIs('bills.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-receipt"></i>
                        <span>Purchase Bills</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-title">Treasury & Catalog</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="{{ route('payments.index') }}" class="nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-money-bill-transfer"></i>
                        <span>Payments & Cash</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span>Products & Services</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-title">Financial Reports</div>
            <ul class="sidebar-nav">
                <li>
                    <a href="{{ route('reports.trial-balance') }}" class="nav-link {{ request()->routeIs('reports.trial-balance') ? 'active' : '' }}">
                        <i class="fa-solid fa-scale-balanced"></i>
                        <span>Trial Balance</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.balance-sheet') }}" class="nav-link {{ request()->routeIs('reports.balance-sheet') ? 'active' : '' }}">
                        <i class="fa-solid fa-landmark"></i>
                        <span>Balance Sheet</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('reports.income-statement') }}" class="nav-link {{ request()->routeIs('reports.income-statement') ? 'active' : '' }}">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Profit & Loss</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-section-title">Administration</div>
            <ul class="sidebar-nav mb-4">
                @if(auth()->user()->isAdmin())
                <li>
                    <a href="{{ route('imports.index') }}" class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-file-arrow-up text-warning"></i>
                        <span>Bulk Import Data</span>
                        <span class="badge bg-warning text-dark ms-auto">Admin</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('fiscal-years.index') }}" class="nav-link {{ request()->routeIs('fiscal-years.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Fiscal Periods</span>
                    </a>
                </li>
                @endif
                @if(auth()->user()->isAdmin() || auth()->user()->isAuditor())
                <li>
                    <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Audit Trail</span>
                    </a>
                </li>
                @endif
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Topbar -->
            <header class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-sm btn-light border d-lg-none" id="sidebarToggle">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <span class="text-muted small d-none d-md-inline">
                        <i class="fa-solid fa-network-wired text-success me-1"></i> Intranet Mode (Local Assets)
                    </span>
                    <span class="badge bg-light text-dark border">
                        <i class="fa-solid fa-database text-info me-1"></i> PostgreSQL 18 Ready
                    </span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-circle-user fs-5 text-primary"></i>
                            <span class="fw-semibold">{{ auth()->user()->name }}</span>
                            <span class="badge bg-secondary text-uppercase">{{ auth()->user()->role }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><h6 class="dropdown-header">{{ auth()->user()->email }}</h6></li>
                            <li><a class="dropdown-item" href="{{ route('profile') }}"><i class="fa-solid fa-user-gear me-2"></i> User Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Sign Out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="main-content">
                <!-- Alerts / Flash Messages -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                        <i class="fa-solid fa-circle-check fs-5"></i>
                        <div>{{ session('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                        <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                        <div>{{ session('error') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                        <i class="fa-solid fa-circle-exclamation fs-5"></i>
                        <div>{{ session('warning') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('info'))
                    <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                        <i class="fa-solid fa-circle-info fs-5"></i>
                        <div>{{ session('info') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <div class="fw-bold mb-1"><i class="fa-solid fa-circle-xmark me-1"></i> Please correct the following errors:</div>
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="bg-white border-top py-3 px-4 text-muted small d-flex justify-content-between align-items-center">
                <div>
                    <strong>ApexAIS</strong> &copy; {{ date('Y') }} - Enterprise Accounting Information System.
                </div>
                <div>
                    Built with <span class="badge bg-light text-dark border">Laravel 13</span> &middot;
                    <span class="badge bg-light text-dark border">PostgreSQL 18</span> &middot;
                    <span class="badge bg-light text-dark border">Bootstrap 5.3</span>
                </div>
            </footer>
        </div>
    </div>

    <!-- Local Offline JS (No CDN) -->
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function () {
            document.getElementById('sidebar-wrapper').classList.toggle('toggled');
        });
    </script>
    @yield('scripts')
</body>
</html>
