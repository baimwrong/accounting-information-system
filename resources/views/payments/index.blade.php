@extends('layouts.app')

@section('title', 'Payments & Treasury')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Cash Receipts & Disbursements</h3>
        <p class="text-muted mb-0 small">Audit trail of bank transactions, customer receipts, and vendor disbursements</p>
    </div>
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
@endsection
