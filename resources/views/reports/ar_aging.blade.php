@extends('layouts.app')

@section('title', 'Accounts Receivable Aging Report')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Accounts Receivable (AR) Aging</h3>
        <p class="text-muted mb-0 small">
            Outstanding customer balances categorized by days past due &middot; As of {{ date('F d, Y', strtotime($as_of_date)) }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.export', ['type' => 'ar_aging', 'as_of_date' => $as_of_date]) }}" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </a>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- As of Date Filter Form -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('reports.ar-aging') }}" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">As of Date</span>
                    <input type="date" name="as_of_date" class="form-control" value="{{ $as_of_date }}">
                </div>
            </div>
            <div class="col-sm-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Calculate Aging</button>
                <a href="{{ route('reports.ar-aging') }}" class="btn btn-outline-secondary btn-sm">Today</a>
            </div>
        </form>
    </div>
</div>

<!-- Executive Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-sm-4">
        <div class="card p-3 border-start border-4 border-success">
            <span class="text-muted small">Current</span>
            <h5 class="fw-bold mb-0 text-success num-col text-start">${{ number_format($totals['current'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card p-3 border-start border-4 border-info">
            <span class="text-muted small">1–30 Days</span>
            <h5 class="fw-bold mb-0 text-info num-col text-start">${{ number_format($totals['days_1_30'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card p-3 border-start border-4 border-warning">
            <span class="text-muted small">31–60 Days</span>
            <h5 class="fw-bold mb-0 text-warning num-col text-start">${{ number_format($totals['days_31_60'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card p-3 border-start border-4 border-secondary">
            <span class="text-muted small">61–90 Days</span>
            <h5 class="fw-bold mb-0 text-secondary num-col text-start">${{ number_format($totals['days_61_90'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card p-3 border-start border-4 border-danger">
            <span class="text-muted small">&gt; 90 Days</span>
            <h5 class="fw-bold mb-0 text-danger num-col text-start">${{ number_format($totals['days_over_90'], 2) }}</h5>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="card p-3 border-start border-4 border-primary">
            <span class="text-muted small">Total AR</span>
            <h5 class="fw-bold mb-0 text-primary num-col text-start">${{ number_format($totals['total'], 2) }}</h5>
        </div>
    </div>
</div>

<!-- AR Aging Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th class="num-col" style="width: 140px;">Current ($)</th>
                    <th class="num-col" style="width: 140px;">1–30 Days ($)</th>
                    <th class="num-col" style="width: 140px;">31–60 Days ($)</th>
                    <th class="num-col" style="width: 140px;">61–90 Days ($)</th>
                    <th class="num-col" style="width: 140px;">&gt; 90 Days ($)</th>
                    <th class="num-col" style="width: 160px;">Total Due ($)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $c)
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">
                            @if($c['customer'])
                                <a href="{{ route('contacts.show', $c['customer']) }}" class="text-decoration-none text-dark">
                                    {{ $c['customer']->name }}
                                </a>
                            @else
                                <span class="text-muted">Unknown Customer</span>
                            @endif
                        </div>
                    </td>
                    <td class="num-col {{ $c['current'] > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                        {{ $c['current'] > 0 ? '$' . number_format($c['current'], 2) : '-' }}
                    </td>
                    <td class="num-col {{ $c['days_1_30'] > 0 ? 'text-dark' : 'text-muted' }}">
                        {{ $c['days_1_30'] > 0 ? '$' . number_format($c['days_1_30'], 2) : '-' }}
                    </td>
                    <td class="num-col {{ $c['days_31_60'] > 0 ? 'text-warning fw-semibold' : 'text-muted' }}">
                        {{ $c['days_31_60'] > 0 ? '$' . number_format($c['days_31_60'], 2) : '-' }}
                    </td>
                    <td class="num-col {{ $c['days_61_90'] > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                        {{ $c['days_61_90'] > 0 ? '$' . number_format($c['days_61_90'], 2) : '-' }}
                    </td>
                    <td class="num-col {{ $c['days_over_90'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                        {{ $c['days_over_90'] > 0 ? '$' . number_format($c['days_over_90'], 2) : '-' }}
                    </td>
                    <td class="num-col fw-bold text-primary">
                        ${{ number_format($c['total'], 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-clipboard-check fs-2 mb-2 d-block text-success"></i>
                        No outstanding customer invoices found as of {{ date('M d, Y', strtotime($as_of_date)) }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold fs-6">
                    <td>Grand Totals:</td>
                    <td class="num-col text-success">${{ number_format($totals['current'], 2) }}</td>
                    <td class="num-col text-dark">${{ number_format($totals['days_1_30'], 2) }}</td>
                    <td class="num-col text-warning">${{ number_format($totals['days_31_60'], 2) }}</td>
                    <td class="num-col text-secondary">${{ number_format($totals['days_61_90'], 2) }}</td>
                    <td class="num-col text-danger">${{ number_format($totals['days_over_90'], 2) }}</td>
                    <td class="num-col text-primary">${{ number_format($totals['total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
