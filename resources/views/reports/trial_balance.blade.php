@extends('layouts.app')

@section('title', 'Trial Balance Report')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Trial Balance (TB)</h3>
        <p class="text-muted mb-0 small">Mathematical verification that total General Ledger debits equal total credits</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('reports.trial-balance') }}" class="row g-2 align-items-center">
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
                <button type="submit" class="btn btn-primary btn-sm">Generate Trial Balance</button>
                <a href="{{ route('reports.trial-balance') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Status Banner -->
<div class="alert {{ $is_balanced ? 'alert-success' : 'alert-danger' }} d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-2">
        <i class="fa-solid {{ $is_balanced ? 'fa-circle-check fs-4' : 'fa-circle-xmark fs-4' }}"></i>
        <div>
            <div class="fw-bold">{{ $is_balanced ? 'TRIAL BALANCE IS BALANCED' : 'WARNING: TRIAL BALANCE OUT OF BALANCE' }}</div>
            <div class="small">{{ $is_balanced ? 'Debits exactly match Credits across all General Ledger accounts.' : 'Total Debits do not equal Total Credits. Discrepancy: $' . number_format($difference, 2) }}</div>
        </div>
    </div>
    <div class="text-end">
        <span class="fs-5 fw-bold font-monospace">${{ number_format($total_debit, 2) }}</span>
    </div>
</div>

<!-- Trial Balance Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">Account Code</th>
                    <th>Account Title</th>
                    <th>Classification</th>
                    <th class="num-col" style="width: 160px;">Debit ($)</th>
                    <th class="num-col" style="width: 160px;">Credit ($)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                @if($row['ending_debit'] > 0 || $row['ending_credit'] > 0)
                <tr>
                    <td class="font-monospace fw-bold text-primary">
                        <a href="{{ route('accounts.statement', $row['account']) }}" class="text-decoration-none">
                            {{ $row['code'] }}
                        </a>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $row['name'] }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border text-capitalize">{{ $row['type'] }}</span>
                    </td>
                    <td class="num-col {{ $row['ending_debit'] > 0 ? 'fw-bold text-dark' : 'text-muted' }}">
                        {{ $row['ending_debit'] > 0 ? '$' . number_format($row['ending_debit'], 2) : '-' }}
                    </td>
                    <td class="num-col {{ $row['ending_credit'] > 0 ? 'fw-bold text-dark' : 'text-muted' }}">
                        {{ $row['ending_credit'] > 0 ? '$' . number_format($row['ending_credit'], 2) : '-' }}
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold fs-6">
                    <td colspan="3" class="text-end">Grand Totals:</td>
                    <td class="num-col text-primary">${{ number_format($total_debit, 2) }}</td>
                    <td class="num-col text-primary">${{ number_format($total_credit, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
