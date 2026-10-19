@extends('layouts.app')

@section('title', 'Statement of Cash Flows')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Statement of Cash Flows</h3>
        <p class="text-muted mb-0 small">
            Indirect Method &middot; Period: {{ date('F d, Y', strtotime($start_date)) }} to {{ date('F d, Y', strtotime($end_date)) }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.export', ['type' => 'cash_flow', 'start_date' => $start_date, 'end_date' => $end_date]) }}" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- Date Filter Form -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('reports.cash-flow') }}" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">From Date</span>
                    <input type="date" name="start_date" class="form-control" value="{{ $start_date }}">
                </div>
            </div>
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">To Date</span>
                    <input type="date" name="end_date" class="form-control" value="{{ $end_date }}">
                </div>
            </div>
            <div class="col-sm-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Calculate Cash Flows</button>
                <a href="{{ route('reports.cash-flow') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-start border-4 {{ $operating_cash_flow >= 0 ? 'border-success' : 'border-danger' }}">
            <span class="text-muted small">Operating Activities</span>
            <h5 class="fw-bold mb-0 {{ $operating_cash_flow >= 0 ? 'text-success' : 'text-danger' }} num-col text-start">
                ${{ number_format($operating_cash_flow, 2) }}
            </h5>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-start border-4 border-info">
            <span class="text-muted small">Investing Activities</span>
            <h5 class="fw-bold mb-0 text-info num-col text-start">
                ${{ number_format($investing_cash_flow, 2) }}
            </h5>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-start border-4 border-warning">
            <span class="text-muted small">Financing Activities</span>
            <h5 class="fw-bold mb-0 text-warning num-col text-start">
                ${{ number_format($financing_cash_flow, 2) }}
            </h5>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-start border-4 {{ $net_cash_change >= 0 ? 'border-primary' : 'border-danger' }}">
            <span class="text-muted small">Net Change in Cash</span>
            <h5 class="fw-bold mb-0 {{ $net_cash_change >= 0 ? 'text-primary' : 'text-danger' }} num-col text-start">
                ${{ number_format($net_cash_change, 2) }}
            </h5>
        </div>
    </div>
</div>

<!-- Statement of Cash Flows Detail Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th>Cash Flow Activity / Description</th>
                    <th class="num-col" style="width: 220px;">Amount ($)</th>
                </tr>
            </thead>
            <tbody>
                <!-- 1. OPERATING ACTIVITIES -->
                <tr class="table-light">
                    <td colspan="2" class="fw-bold text-uppercase text-secondary py-2">
                        <i class="fa-solid fa-briefcase me-1"></i> Cash Flows from Operating Activities
                    </td>
                </tr>
                <tr>
                    <td class="ps-4 fw-semibold text-dark">Net Income (Bottom Line Profit / Loss)</td>
                    <td class="num-col {{ $net_income >= 0 ? 'text-dark' : 'text-danger' }}">
                        ${{ number_format($net_income, 2) }}
                    </td>
                </tr>
                @if($depreciation > 0)
                <tr>
                    <td class="ps-4 text-muted">Adjustments for non-cash items: Depreciation &amp; Amortization</td>
                    <td class="num-col text-success">
                        +${{ number_format($depreciation, 2) }}
                    </td>
                </tr>
                @endif
                @forelse($operating_adjustments as $adj)
                <tr>
                    <td class="ps-4 text-muted">{{ $adj['name'] }}</td>
                    <td class="num-col {{ $adj['amount'] < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $adj['amount'] < 0 ? '-$' . number_format(abs($adj['amount']), 2) : '+$' . number_format($adj['amount'], 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="ps-4 text-muted fst-italic">No working capital adjustments recorded in this period.</td>
                </tr>
                @endforelse
                <tr class="fw-bold border-top border-bottom">
                    <td class="ps-3 text-dark">Net Cash Provided by (Used in) Operating Activities</td>
                    <td class="num-col {{ $operating_cash_flow >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($operating_cash_flow, 2) }}
                    </td>
                </tr>

                <!-- 2. INVESTING ACTIVITIES -->
                <tr class="table-light">
                    <td colspan="2" class="fw-bold text-uppercase text-secondary py-2">
                        <i class="fa-solid fa-building me-1"></i> Cash Flows from Investing Activities
                    </td>
                </tr>
                @forelse($investing_activities as $inv)
                <tr>
                    <td class="ps-4 text-muted">{{ $inv['name'] }}</td>
                    <td class="num-col {{ $inv['amount'] < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $inv['amount'] < 0 ? '-$' . number_format(abs($inv['amount']), 2) : '+$' . number_format($inv['amount'], 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="ps-4 text-muted fst-italic">No capital investing activities recorded during this period.</td>
                </tr>
                @endforelse
                <tr class="fw-bold border-top border-bottom">
                    <td class="ps-3 text-dark">Net Cash Provided by (Used in) Investing Activities</td>
                    <td class="num-col {{ $investing_cash_flow >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($investing_cash_flow, 2) }}
                    </td>
                </tr>

                <!-- 3. FINANCING ACTIVITIES -->
                <tr class="table-light">
                    <td colspan="2" class="fw-bold text-uppercase text-secondary py-2">
                        <i class="fa-solid fa-coins me-1"></i> Cash Flows from Financing Activities
                    </td>
                </tr>
                @forelse($financing_activities as $fin)
                <tr>
                    <td class="ps-4 text-muted">{{ $fin['name'] }}</td>
                    <td class="num-col {{ $fin['amount'] < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $fin['amount'] < 0 ? '-$' . number_format(abs($fin['amount']), 2) : '+$' . number_format($fin['amount'], 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="2" class="ps-4 text-muted fst-italic">No debt or equity financing activities recorded during this period.</td>
                </tr>
                @endforelse
                <tr class="fw-bold border-top border-bottom">
                    <td class="ps-3 text-dark">Net Cash Provided by (Used in) Financing Activities</td>
                    <td class="num-col {{ $financing_cash_flow >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($financing_cash_flow, 2) }}
                    </td>
                </tr>

                <!-- 4. NET CHANGE & RECONCILIATION -->
                <tr class="table-primary fw-bold fs-6">
                    <td>Net Increase (Decrease) in Cash &amp; Cash Equivalents</td>
                    <td class="num-col {{ $net_cash_change >= 0 ? 'text-primary' : 'text-danger' }}">
                        ${{ number_format($net_cash_change, 2) }}
                    </td>
                </tr>
                <tr>
                    <td class="ps-3 text-muted">Cash and cash equivalents at beginning of period</td>
                    <td class="num-col fw-semibold">${{ number_format($beginning_cash, 2) }}</td>
                </tr>
                <tr class="table-light fw-bold fs-6 border-top border-dark border-2">
                    <td>Cash and cash equivalents at end of period</td>
                    <td class="num-col text-primary font-monospace">${{ number_format($ending_cash, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
