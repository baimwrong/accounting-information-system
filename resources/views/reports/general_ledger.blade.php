@extends('layouts.app')

@section('title', 'General Ledger Report')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">General Ledger (GL)</h3>
        <p class="text-muted mb-0 small">Master ledger statement tracking chronologically all posted debits, credits, and running balance</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print GL Statement
        </button>
    </div>
</div>

<!-- Account & Date Range Filter -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('reports.general-ledger') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Select Account</span>
                    <select name="account_id" class="form-select" onchange="this.form.submit()">
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $selectedAccountId == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">From</span>
                    <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">To</span>
                    <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                </div>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">View</button>
            </div>
        </form>
    </div>
</div>

@if($ledgerData)
<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card p-3">
            <span class="text-muted small">Starting Balance</span>
            <h5 class="fw-bold mb-0 num-col text-start">${{ number_format($ledgerData['opening_balance'], 2) }}</h5>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3">
            <span class="text-muted small">Period Movements</span>
            <h5 class="fw-bold mb-0 num-col text-start">
                DR: ${{ number_format($ledgerData['total_debit'], 2) }} / CR: ${{ number_format($ledgerData['total_credit'], 2) }}
            </h5>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3 border-start border-4 border-primary">
            <span class="text-muted small">Current Net Balance</span>
            <h5 class="fw-bold mb-0 num-col text-start text-primary">${{ number_format($ledgerData['ending_balance'], 2) }}</h5>
        </div>
    </div>
</div>

<!-- Ledger Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 110px;">Date</th>
                    <th style="width: 150px;">Voucher #</th>
                    <th>Reference</th>
                    <th>Transaction Description</th>
                    <th class="num-col" style="width: 140px;">Debit ($)</th>
                    <th class="num-col" style="width: 140px;">Credit ($)</th>
                    <th class="num-col" style="width: 150px;">Running Balance ($)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light">
                    <td colspan="6" class="fw-bold">Opening Balance</td>
                    <td class="num-col fw-bold">${{ number_format($ledgerData['opening_balance'], 2) }}</td>
                </tr>
                @forelse($ledgerData['lines'] as $line)
                <tr>
                    <td>{{ $line['date'] }}</td>
                    <td>
                        <a href="{{ route('journal-entries.show', $line['journal_entry_id']) }}" class="fw-bold text-decoration-none font-monospace">
                            {{ $line['entry_number'] }}
                        </a>
                    </td>
                    <td><span class="text-muted small">{{ $line['reference'] ?: '-' }}</span></td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num-col {{ $line['debit'] > 0 ? 'fw-bold text-dark' : 'text-muted' }}">
                        {{ $line['debit'] > 0 ? '$' . number_format($line['debit'], 2) : '-' }}
                    </td>
                    <td class="num-col {{ $line['credit'] > 0 ? 'fw-bold text-dark' : 'text-muted' }}">
                        {{ $line['credit'] > 0 ? '$' . number_format($line['credit'], 2) : '-' }}
                    </td>
                    <td class="num-col fw-bold text-primary">
                        ${{ number_format($line['running_balance'], 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No transactions recorded for this account.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold fs-6">
                    <td colspan="4" class="text-end">Ending Period Total:</td>
                    <td class="num-col">${{ number_format($ledgerData['total_debit'], 2) }}</td>
                    <td class="num-col">${{ number_format($ledgerData['total_credit'], 2) }}</td>
                    <td class="num-col text-primary">${{ number_format($ledgerData['ending_balance'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif
@endsection
