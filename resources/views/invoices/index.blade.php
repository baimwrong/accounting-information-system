@extends('layouts.app')

@section('title', 'Sales Invoices')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Sales Invoices (Accounts Receivable)</h3>
        <p class="text-muted mb-0 small">Billing customers, tracking revenue, and auto-posting AR journal entries</p>
    </div>
    @if(auth()->user()->isAccountant())
    <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i> New Sales Invoice
    </a>
    @endif
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('invoices.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Status</span>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent / Unpaid</option>
                        <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Customer</span>
                    <select name="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach($customers as $cust)
                            <option value="{{ $cust->id }}" {{ request('customer_id') == $cust->id ? 'selected' : '' }}>
                                {{ $cust->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice # or Customer name" value="{{ request('search') }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 150px;">Invoice #</th>
                    <th>Customer</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th class="num-col">Total</th>
                    <th class="num-col">Paid</th>
                    <th class="num-col">Balance Due</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td>
                        <a href="{{ route('invoices.show', $inv) }}" class="fw-bold font-monospace text-decoration-none">
                            {{ $inv->invoice_number }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('contacts.show', $inv->customer) }}" class="text-dark fw-semibold text-decoration-none">
                            {{ $inv->customer->name }}
                        </a>
                    </td>
                    <td>{{ $inv->issue_date->format('M d, Y') }}</td>
                    <td>
                        <span class="{{ $inv->due_date->isPast() && !$inv->isPaid() ? 'text-danger fw-semibold' : '' }}">
                            {{ $inv->due_date->format('M d, Y') }}
                        </span>
                    </td>
                    <td class="num-col fw-bold">${{ number_format($inv->total_amount, 2) }}</td>
                    <td class="num-col">${{ number_format($inv->paid_amount, 2) }}</td>
                    <td class="num-col fw-bold {{ $inv->remaining_amount > 0 ? 'text-danger' : 'text-success' }}">
                        ${{ number_format($inv->remaining_amount, 2) }}
                    </td>
                    <td>
                        @if($inv->status === 'paid')
                            <span class="badge bg-success">PAID</span>
                        @elseif($inv->status === 'partially_paid')
                            <span class="badge bg-info text-dark">PARTIAL</span>
                        @elseif($inv->status === 'sent')
                            <span class="badge bg-primary">SENT</span>
                        @else
                            <span class="badge bg-secondary text-uppercase">{{ $inv->status }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('invoices.show', $inv) }}" class="btn btn-outline-primary btn-sm" title="View / Pay Invoice">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No invoices on record.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($invoices->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $invoices->links() }}
    </div>
    @endif
</div>
@endsection
