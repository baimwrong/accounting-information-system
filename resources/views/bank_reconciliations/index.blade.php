@extends('layouts.app')

@section('title', 'Bank Reconciliations')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Bank Statement Reconciliations</h3>
        <p class="text-muted mb-0 small">Verify cash and bank ledger balances against official commercial bank statements</p>
    </div>
    @if(auth()->user()->isAccountant())
    <a href="{{ route('bank-reconciliations.create') }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i> Start New Reconciliation
    </a>
    @endif
</div>

<!-- Filter card -->
<div class="card p-3 mb-4 shadow-sm border">
    <form method="GET" action="{{ route('bank-reconciliations.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <select name="bank_account_id" class="form-select form-select-sm">
                <option value="">All Cash & Bank Accounts</option>
                @foreach($bankAccounts as $acc)
                    <option value="{{ $acc->id }}" {{ request('bank_account_id') == $acc->id ? 'selected' : '' }}>
                        {{ $acc->code }} - {{ $acc->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>In Progress (Draft)</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed & Locked</option>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            <a href="{{ route('bank-reconciliations.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="card shadow-sm border">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th>Bank Account</th>
                    <th>Statement Date</th>
                    <th class="num-col">Statement Balance</th>
                    <th class="num-col">Cleared Balance</th>
                    <th class="num-col">Difference</th>
                    <th>Status</th>
                    <th>Completed By</th>
                    <th class="text-end" style="width: 120px;">Worksheet</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reconciliations as $rec)
                <tr>
                    <td>
                        <span class="font-monospace small fw-bold">{{ $rec->bankAccount->code }}</span> - {{ $rec->bankAccount->name }}
                    </td>
                    <td>{{ $rec->statement_date->format('M d, Y') }}</td>
                    <td class="num-col fw-bold">${{ number_format($rec->statement_balance, 2) }}</td>
                    <td class="num-col">${{ number_format($rec->reconciled_balance, 2) }}</td>
                    <td class="num-col">
                        @if(abs($rec->difference) < 0.01)
                            <span class="text-success fw-bold">$0.00</span>
                        @else
                            <span class="text-danger fw-bold">${{ number_format($rec->difference, 2) }}</span>
                        @endif
                    </td>
                    <td>
                        @if($rec->isCompleted())
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                <i class="fa-solid fa-lock me-1"></i> LOCKED
                            </span>
                        @else
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">
                                <i class="fa-solid fa-clock me-1"></i> IN PROGRESS
                            </span>
                        @endif
                    </td>
                    <td>{{ $rec->completer ? $rec->completer->name : '-' }}</td>
                    <td class="text-end">
                        <a href="{{ route('bank-reconciliations.show', $rec) }}" class="btn btn-outline-primary btn-sm">
                            <i class="fa-solid fa-calculator me-1"></i> Open
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No bank reconciliation sessions recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reconciliations->hasPages())
    <div class="p-3 border-top">
        {{ $reconciliations->links() }}
    </div>
    @endif
</div>
@endsection
