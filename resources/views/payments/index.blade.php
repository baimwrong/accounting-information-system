@extends('layouts.app')

@section('title', 'Payments & Treasury')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Cash Receipts & Disbursements</h3>
        <p class="text-muted mb-0 small">Audit trail of bank transactions, customer receipts, and vendor disbursements</p>
    </div>
    @if(in_array(auth()->user()->role, ['admin', 'accountant']))
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
            <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Inter-Bank Transfer
        </button>
    </div>
    @endif
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('payments.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Flow Type</span>
                    <select name="type" class="form-select">
                        <option value="">All Flows</option>
                        <option value="received" {{ request('type') === 'received' ? 'selected' : '' }}>Customer Receipts</option>
                        <option value="sent" {{ request('type') === 'sent' ? 'selected' : '' }}>Vendor Disbursements</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Payment #, Reference, or Contact name" value="{{ request('search') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">Payment #</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Contact</th>
                    <th>Bank / Cash Account</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="num-col">Amount</th>
                    <th class="text-end" style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $p)
                <tr>
                    <td>
                        <a href="{{ route('payments.show', $p) }}" class="fw-bold font-monospace text-decoration-none">
                            {{ $p->payment_number }}
                        </a>
                    </td>
                    <td>
                        @if($p->type === 'received')
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Receipt</span>
                        @else
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Disbursement</span>
                        @endif
                    </td>
                    <td>{{ $p->payment_date->format('M d, Y') }}</td>
                    <td>
                        <a href="{{ route('contacts.show', $p->contact) }}" class="text-dark fw-semibold text-decoration-none">
                            {{ $p->contact->name }}
                        </a>
                    </td>
                    <td>{{ $p->bankAccount->code }} - {{ $p->bankAccount->name }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $p->payment_method }}</span></td>
                    <td><span class="text-muted small">{{ $p->reference ?: '-' }}</span></td>
                    <td class="num-col fw-bold {{ $p->type === 'received' ? 'text-success' : 'text-danger' }}">
                        {{ $p->type === 'received' ? '+' : '-' }}${{ number_format($p->amount, 2) }}
                    </td>
                    <td class="text-end">
                        <a href="{{ route('payments.show', $p) }}" class="btn btn-outline-primary btn-sm" title="View Voucher">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No payment records found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($payments->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $payments->links() }}
    </div>
    @endif
</div>

@if(in_array(auth()->user()->role, ['admin', 'accountant']))
<!-- Inter-Bank Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('payments.transfer') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="transferModalLabel">
                        <i class="fa-solid fa-arrow-right-arrow-left text-primary me-2"></i>Inter-Bank Fund Transfer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Move funds between cash and bank accounts. An automated balanced General Ledger journal entry will be recorded and posted immediately.
                    </p>
                    
                    <div class="mb-3">
                        <label for="source_account_id" class="form-label small fw-semibold">Source Account (Transfer Out / Credit)</label>
                        <select name="source_account_id" id="source_account_id" class="form-select form-select-sm" required>
                            <option value="">Select Originating Account...</option>
                            @foreach($bankAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('source_account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="destination_account_id" class="form-label small fw-semibold">Destination Account (Transfer In / Debit)</label>
                        <select name="destination_account_id" id="destination_account_id" class="form-select form-select-sm" required>
                            <option value="">Select Destination Account...</option>
                            @foreach($bankAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('destination_account_id') == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label for="amount" class="form-label small fw-semibold">Amount ($)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="date" class="form-label small fw-semibold">Transfer Date</label>
                            <input type="date" name="date" id="date" class="form-control form-control-sm" value="{{ old('date', date('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="reference" class="form-label small fw-semibold">Reference / Wire # (Optional)</label>
                        <input type="text" name="reference" id="reference" class="form-control form-control-sm" value="{{ old('reference') }}" placeholder="e.g. WIRE-84920">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold">Description / Memo (Optional)</label>
                        <input type="text" name="description" id="description" class="form-control form-control-sm" value="{{ old('description') }}" placeholder="e.g. Funding payroll operating account">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-check me-1"></i> Post Bank Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
