@extends('layouts.app')

@section('title', 'Balance Sheet')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Balance Sheet (Statement of Financial Position)</h3>
        <p class="text-muted mb-0 small">As of {{ date('F d, Y', strtotime($as_of_date)) }} &middot; Fundamental Equation: Assets = Liabilities + Equity</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- As of Date Filter -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('reports.balance-sheet') }}" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">As of Date</span>
                    <input type="date" name="as_of_date" class="form-control" value="{{ $as_of_date }}">
                </div>
            </div>
            <div class="col-sm-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Update Statement</button>
                <a href="{{ route('reports.balance-sheet') }}" class="btn btn-outline-secondary btn-sm">Today</a>
            </div>
        </form>
    </div>
</div>

<!-- Accounting Equation Verification Box -->
<div class="alert {{ $is_balanced ? 'alert-success' : 'alert-danger' }} d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
        <i class="fa-solid {{ $is_balanced ? 'fa-scale-balanced fs-4' : 'fa-triangle-exclamation fs-4' }}"></i>
        <div>
            <div class="fw-bold">{{ $is_balanced ? 'BALANCE SHEET IN EQUILIBRIUM' : 'BALANCE SHEET OUT OF EQUILIBRIUM' }}</div>
            <div class="small">
                Assets (${{ number_format($total_assets, 2) }}) =
                Liabilities (${{ number_format($total_liabilities, 2) }}) +
                Equity (${{ number_format($total_equity, 2) }})
            </div>
        </div>
    </div>
    <div class="text-end">
        <span class="badge {{ $is_balanced ? 'bg-success' : 'bg-danger' }} fs-6">
            {{ $is_balanced ? 'Balanced' : 'Diff: $' . number_format($difference, 2) }}
        </span>
    </div>
</div>

<div class="row g-4">
    <!-- ASSETS COLUMN -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-5">1. ASSETS</span>
                <span class="font-monospace">${{ number_format($total_assets, 2) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Asset Account Title</th>
                            <th class="num-col">Balance ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assets as $item)
                        @if($item['balance'] != 0)
                        <tr>
                            <td class="font-monospace text-primary fw-bold">{{ $item['account']->code }}</td>
                            <td>{{ $item['account']->name }}</td>
                            <td class="num-col">${{ number_format($item['balance'], 2) }}</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold fs-6 border-top">
                            <td colspan="2">TOTAL ASSETS</td>
                            <td class="num-col text-primary">${{ number_format($total_assets, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- LIABILITIES & EQUITY COLUMN -->
    <div class="col-lg-6">
        <!-- LIABILITIES -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-5">2. LIABILITIES</span>
                <span class="font-monospace">${{ number_format($total_liabilities, 2) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Liability Account Title</th>
                            <th class="num-col">Balance ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($liabilities as $item)
                        @if($item['balance'] != 0)
                        <tr>
                            <td class="font-monospace text-danger fw-bold">{{ $item['account']->code }}</td>
                            <td>{{ $item['account']->name }}</td>
                            <td class="num-col">${{ number_format($item['balance'], 2) }}</td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold border-top">
                            <td colspan="2">TOTAL LIABILITIES</td>
                            <td class="num-col text-danger">${{ number_format($total_liabilities, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- EQUITY -->
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-5">3. OWNER'S EQUITY</span>
                <span class="font-monospace">${{ number_format($total_equity, 2) }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Equity Account Title</th>
                            <th class="num-col">Balance ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($equity as $item)
                        @if($item['balance'] != 0)
                        <tr>
                            <td class="font-monospace text-success fw-bold">{{ $item['account']->code }}</td>
                            <td>{{ $item['account']->name }}</td>
                            <td class="num-col">${{ number_format($item['balance'], 2) }}</td>
                        </tr>
                        @endif
                        @endforeach
                        <!-- Current Year Retained Earnings / Net Income -->
                        <tr class="table-info bg-opacity-25">
                            <td class="font-monospace text-muted fw-bold">3999</td>
                            <td class="fw-bold">Current Period Net Income (Retained)</td>
                            <td class="num-col fw-bold text-success">${{ number_format($current_year_earnings, 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-bold border-top">
                            <td colspan="2">TOTAL EQUITY</td>
                            <td class="num-col text-success">${{ number_format($total_equity, 2) }}</td>
                        </tr>
                        <tr class="table-dark fw-bold fs-6">
                            <td colspan="2">TOTAL LIABILITIES & EQUITY</td>
                            <td class="num-col">${{ number_format($total_liabilities_and_equity, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
