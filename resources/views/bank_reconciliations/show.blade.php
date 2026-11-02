@extends('layouts.app')

@section('title', 'Reconciliation Worksheet - ' . $bankReconciliation->bankAccount->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h3 class="fw-bold mb-0">Reconciliation: {{ $bankReconciliation->bankAccount->name }}</h3>
            @if($bankReconciliation->isCompleted())
                <span class="badge bg-success bg-opacity-10 text-success border border-success">
                    <i class="fa-solid fa-lock me-1"></i> RECONCILED & LOCKED
                </span>
            @else
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">
                    <i class="fa-solid fa-clock me-1"></i> DRAFT WORKSHEET
                </span>
            @endif
        </div>
        <p class="text-muted mb-0 small">
            Account: <span class="font-monospace fw-bold">{{ $bankReconciliation->bankAccount->code }}</span> &middot;
            Cut-Off Statement Date: <strong>{{ $bankReconciliation->statement_date->format('F d, Y') }}</strong>
            @if($bankReconciliation->isCompleted())
                &middot; Verified By: <strong>{{ $bankReconciliation->completer?->name }}</strong> on {{ $bankReconciliation->completed_at?->format('M d, Y H:i') }}
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 d-print-none">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Worksheet
        </button>
        <a href="{{ route('bank-reconciliations.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> All Reconciliations
        </a>
    </div>
</div>

<!-- Financial Reconciliation Summary Metrics -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border h-100">
            <div class="text-muted small fw-bold text-uppercase mb-1">1. Bank Statement Balance</div>
            <div class="fs-4 fw-bold text-dark">${{ number_format($bankReconciliation->statement_balance, 2) }}</div>
            <div class="small text-muted">Ending target balance</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border h-100">
            <div class="text-muted small fw-bold text-uppercase mb-1">2. Cleared Transactions</div>
            <div class="d-flex justify-content-between small text-success">
                <span>Receipts (+):</span>
                <strong>+${{ number_format($clearedReceipts, 2) }}</strong>
            </div>
            <div class="d-flex justify-content-between small text-danger mb-1">
                <span>Disbursements (-):</span>
                <strong>-${{ number_format($clearedDisbursements, 2) }}</strong>
            </div>
            <div class="border-top pt-1 fw-bold text-dark d-flex justify-content-between">
                <span>Net Cleared:</span>
                <span>${{ number_format($clearedBalance, 2) }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 shadow-sm border h-100">
            <div class="text-muted small fw-bold text-uppercase mb-1">3. Cleared Balance</div>
            <div class="fs-4 fw-bold text-primary">${{ number_format($clearedBalance, 2) }}</div>
            <div class="small text-muted">Sum of selected cleared records</div>
        </div>
    </div>
    <div class="col-md-3">
        @if(abs($difference) < 0.01)
        <div class="card p-3 shadow-sm border border-success bg-success bg-opacity-10 h-100">
            <div class="text-success small fw-bold text-uppercase mb-1"><i class="fa-solid fa-circle-check me-1"></i> 4. Difference</div>
            <div class="fs-4 fw-bold text-success">$0.00</div>
            <div class="small text-success fw-semibold">Worksheet is perfectly balanced!</div>
        </div>
        @else
        <div class="card p-3 shadow-sm border border-danger bg-danger bg-opacity-10 h-100">
            <div class="text-danger small fw-bold text-uppercase mb-1"><i class="fa-solid fa-circle-xmark me-1"></i> 4. Difference</div>
            <div class="fs-4 fw-bold text-danger">${{ number_format($difference, 2) }}</div>
            <div class="small text-danger">Unresolved discrepancy</div>
        </div>
        @endif
    </div>
</div>

<!-- Controls and Action Toolbar -->
@if($bankReconciliation->isDraft() && auth()->user()->isAccountant())
<div class="card p-3 mb-4 shadow-sm border d-print-none">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex gap-2">
            <form action="{{ route('bank-reconciliations.clear-all', $bankReconciliation) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm">
                    <i class="fa-solid fa-check-double me-1"></i> Mark All Cleared
                </button>
            </form>
            <form action="{{ route('bank-reconciliations.unclear-all', $bankReconciliation) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-xmark me-1"></i> Reset Selection
                </button>
            </form>
        </div>

        <div>
            @if(abs($difference) < 0.01)
            <form action="{{ route('bank-reconciliations.complete', $bankReconciliation) }}" method="POST" onsubmit="return confirm('Lock this bank reconciliation? No further modifications will be permitted.');">
                @csrf
                <button type="submit" class="btn btn-success btn-sm fw-bold">
                    <i class="fa-solid fa-lock me-1"></i> Verify & Lock Reconciliation
                </button>
            </form>
            @else
            <button class="btn btn-secondary btn-sm" disabled title="Difference must be $0.00 before locking">
                <i class="fa-solid fa-lock me-1"></i> Lock Reconciliation (Requires $0.00 Difference)
            </button>
            @endif
        </div>
    </div>
</div>
@endif

<!-- Transactions Worksheet Table -->
<div class="card shadow-sm border">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold"><i class="fa-solid fa-list-check me-2 text-primary"></i> Transactions Up to {{ $bankReconciliation->statement_date->format('M d, Y') }}</span>
        <span class="badge bg-secondary">{{ $payments->count() }} Payments Recorded</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 100px;">Status</th>
                    <th>Date</th>
                    <th>Payment Number</th>
                    <th>Contact</th>
                    <th>Method & Ref</th>
                    <th class="num-col">Receipt / Deposit (+)</th>
                    <th class="num-col">Disbursement (-)</th>
                    @if($bankReconciliation->isDraft())
                    <th class="text-end d-print-none" style="width: 120px;">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                @php
                    $isCleared = $p->bank_reconciliation_id === $bankReconciliation->id;
                @endphp
                <tr class="{{ $isCleared ? 'table-success bg-opacity-10' : '' }}">
                    <td>
                        @if($isCleared)
                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> CLEARED</span>
                        @else
                            <span class="badge bg-light text-muted border">UNCLEARED</span>
                        @endif
                    </td>
                    <td>{{ $p->payment_date->format('M d, Y') }}</td>
                    <td class="font-monospace fw-semibold">{{ $p->payment_number }}</td>
                    <td>{{ $p->contact?->name ?: 'N/A' }}</td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $p->payment_method }}</span>
                        @if($p->reference)
                            <span class="small text-muted ms-1">Ref: {{ $p->reference }}</span>
                        @endif
                    </td>
                    <td class="num-col">
                        @if($p->type === 'received')
                            <span class="text-success fw-bold">+${{ number_format($p->amount, 2) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="num-col">
                        @if($p->type === 'sent')
                            <span class="text-danger fw-bold">-${{ number_format($p->amount, 2) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    @if($bankReconciliation->isDraft())
                    <td class="text-end d-print-none">
                        <form action="{{ route('bank-reconciliations.toggle', [$bankReconciliation, $p]) }}" method="POST">
                            @csrf
                            @if($isCleared)
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Unclear transaction">
                                    <i class="fa-solid fa-minus"></i> Unclear
                                </button>
                            @else
                                <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Clear transaction">
                                    <i class="fa-solid fa-check"></i> Clear
                                </button>
                            @endif
                        </form>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No payments recorded for this bank account on or before the statement date.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
