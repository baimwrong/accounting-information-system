@extends('layouts.app')

@section('title', 'Ledger Statement - ' . $account->code)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">General Ledger Statement: {{ $account->code }} - {{ $account->name }}</h3>
        <p class="text-muted mb-0 small">
            Type: <span class="badge bg-light text-dark border">{{ ucfirst($account->type) }}</span> &middot;
            Normal Balance: <span class="badge bg-light text-dark border">{{ strtoupper($account->normal_balance) }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Statement
        </button>
        <a href="{{ route('accounts.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Accounts
        </a>
    </div>
</div>

<!-- Date Filter Form -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('accounts.statement', $account) }}" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">From</span>
                    <input type="date" name="start_date" class="form-control" value="{{ $start_date }}">
                </div>
            </div>
            <div class="col-sm-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">To</span>
                    <input type="date" name="end_date" class="form-control" value="{{ $end_date }}">
                </div>
            </div>
            <div class="col-sm-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('accounts.statement', $account) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Statement Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card p-3">
            <span class="text-muted small">Opening Balance</span>
            <h5 class="fw-bold mb-0 num-col text-start">${{ number_format($opening_balance, 2) }}</h5>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3">
            <span class="text-muted small">Period Activity</span>
            <h5 class="fw-bold mb-0 num-col text-start">
                DR: ${{ number_format($total_debit, 2) }} / CR: ${{ number_format($total_credit, 2) }}
            </h5>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card p-3 border-start border-4 border-primary">
            <span class="text-muted small">Ending Balance</span>
            <h5 class="fw-bold mb-0 num-col text-start text-primary">${{ number_format($ending_balance, 2) }}</h5>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 110px;">Date</th>
                    <th style="width: 140px;">Entry #</th>
                    <th>Reference</th>
                    <th>Description / Memo</th>
                    <th class="num-col" style="width: 130px;">Debit</th>
                    <th class="num-col" style="width: 130px;">Credit</th>
                    <th class="num-col" style="width: 140px;">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light">
                    <td colspan="6" class="fw-bold">Opening Balance</td>
                    <td class="num-col fw-bold">${{ number_format($opening_balance, 2) }}</td>
                </tr>
                @forelse($lines as $line)
                <tr>
                    <td>{{ $line['date'] }}</td>
                    <td>
                        <a href="{{ route('journal-entries.show', $line['journal_entry_id']) }}" class="fw-semibold text-decoration-none">
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
                    <td colspan="7" class="text-center text-muted py-4">No transactions recorded in this period.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="4" class="text-end">Ending Total:</td>
                    <td class="num-col">${{ number_format($total_debit, 2) }}</td>
                    <td class="num-col">${{ number_format($total_credit, 2) }}</td>
                    <td class="num-col text-primary">${{ number_format($ending_balance, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
