@extends('layouts.app')

@section('title', 'Income Statement (P&L)')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Income Statement (Profit & Loss)</h3>
        <p class="text-muted mb-0 small">
            Period: {{ date('F d, Y', strtotime($start_date)) }} to {{ date('F d, Y', strtotime($end_date)) }} &middot;
            Operating performance and bottom-line margin
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- Date Filter Form -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('reports.income-statement') }}" class="row g-2 align-items-center">
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
                <button type="submit" class="btn btn-primary btn-sm">Calculate P&L</button>
                <a href="{{ route('reports.income-statement') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Executive Summary Banner -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card p-3 border-start border-4 border-primary">
            <span class="text-muted small">Total Operating Revenue</span>
            <h4 class="fw-bold mb-0 text-primary num-col text-start">${{ number_format($total_revenue, 2) }}</h4>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3 border-start border-4 border-info">
            <span class="text-muted small">Gross Profit (Margin: {{ $total_revenue > 0 ? number_format(($gross_profit / $total_revenue) * 100, 1) : 0 }}%)</span>
            <h4 class="fw-bold mb-0 text-info num-col text-start">${{ number_format($gross_profit, 2) }}</h4>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3 border-start border-4 {{ $net_income >= 0 ? 'border-success' : 'border-danger' }}">
            <span class="text-muted small">Net Income (Bottom Line)</span>
            <h4 class="fw-bold mb-0 {{ $net_income >= 0 ? 'text-success' : 'text-danger' }} num-col text-start">${{ number_format($net_income, 2) }}</h4>
        </div>
    </div>
</div>

<!-- Detailed Statement Sheet -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <tbody>
                <!-- 1. REVENUE SECTION -->
                <tr class="table-light">
                    <th colspan="2" class="fs-6 text-primary">OPERATING REVENUE</th>
                    <th class="num-col fs-6 text-primary">AMOUNT ($)</th>
                </tr>
                @forelse($revenues as $rev)
                @if($rev['balance'] != 0)
                <tr>
                    <td style="width: 120px;" class="font-monospace text-muted">{{ $rev['account']->code }}</td>
                    <td class="ps-4">{{ $rev['account']->name }}</td>
                    <td class="num-col">${{ number_format($rev['balance'], 2) }}</td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="3" class="text-muted ps-4">No revenue entries recorded for this period.</td>
                </tr>
                @endforelse
                <tr class="fw-bold bg-light">
                    <td colspan="2" class="text-end">TOTAL REVENUE:</td>
                    <td class="num-col text-primary fs-6">${{ number_format($total_revenue, 2) }}</td>
                </tr>

                <!-- 2. COST OF GOODS SOLD (COGS) -->
                <tr class="table-light">
                    <th colspan="2" class="fs-6 text-danger">COST OF GOODS SOLD (COGS)</th>
                    <th class="num-col fs-6 text-danger">AMOUNT ($)</th>
                </tr>
                @forelse($cogs as $c)
                @if($c['balance'] != 0)
                <tr>
                    <td class="font-monospace text-muted">{{ $c['account']->code }}</td>
                    <td class="ps-4">{{ $c['account']->name }}</td>
                    <td class="num-col">${{ number_format($c['balance'], 2) }}</td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="3" class="text-muted ps-4">No direct cost of goods sold recorded.</td>
                </tr>
                @endforelse
                <tr class="fw-bold bg-light">
                    <td colspan="2" class="text-end">TOTAL COGS:</td>
                    <td class="num-col text-danger fs-6">${{ number_format($total_cogs, 2) }}</td>
                </tr>

                <!-- GROSS PROFIT SUB-TOTAL -->
                <tr class="table-info fw-bold fs-6">
                    <td colspan="2" class="text-uppercase">GROSS PROFIT (REVENUE - COGS):</td>
                    <td class="num-col">${{ number_format($gross_profit, 2) }}</td>
                </tr>

                <!-- 3. OPERATING EXPENSES -->
                <tr class="table-light">
                    <th colspan="2" class="fs-6 text-warning text-dark">OPERATING & ADMINISTRATIVE EXPENSES</th>
                    <th class="num-col fs-6 text-dark">AMOUNT ($)</th>
                </tr>
                @forelse($operating_expenses as $exp)
                @if($exp['balance'] != 0)
                <tr>
                    <td class="font-monospace text-muted">{{ $exp['account']->code }}</td>
                    <td class="ps-4">{{ $exp['account']->name }}</td>
                    <td class="num-col">${{ number_format($exp['balance'], 2) }}</td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="3" class="text-muted ps-4">No operating expenses recorded for this period.</td>
                </tr>
                @endforelse
                <tr class="fw-bold bg-light">
                    <td colspan="2" class="text-end">TOTAL OPERATING EXPENSES:</td>
                    <td class="num-col text-danger fs-6">${{ number_format($total_operating_expenses, 2) }}</td>
                </tr>

                <!-- 4. NET INCOME / LOSS (BOTTOM LINE) -->
                <tr class="{{ $net_income >= 0 ? 'table-success' : 'table-danger' }} fw-bold fs-5 border-top border-3">
                    <td colspan="2" class="text-uppercase">NET INCOME / (NET LOSS):</td>
                    <td class="num-col {{ $net_income >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($net_income, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
