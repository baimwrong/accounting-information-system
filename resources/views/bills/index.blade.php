@extends('layouts.app')

@section('title', 'Purchase Bills')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Purchase Bills (Accounts Payable)</h3>
        <p class="text-muted mb-0 small">Vendor invoices, operating expenditures, and automated AP double-entry posting</p>
    </div>
    @if(auth()->user()->isAccountant())
    <a href="{{ route('bills.create') }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i> New Purchase Bill
    </a>
    @endif
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('bills.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Status</span>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved / Unpaid</option>
                        <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Vendor</span>
                    <select name="vendor_id" class="form-select">
                        <option value="">All Vendors</option>
                        @foreach($vendors as $ven)
                            <option value="{{ $ven->id }}" {{ request('vendor_id') == $ven->id ? 'selected' : '' }}>
                                {{ $ven->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Bill # or Vendor name" value="{{ request('search') }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Bills Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 150px;">Bill #</th>
                    <th>Vendor / Supplier</th>
                    <th>Bill Date</th>
                    <th>Due Date</th>
                    <th class="num-col">Total</th>
                    <th class="num-col">Paid</th>
                    <th class="num-col">Balance Due</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bills as $b)
                <tr>
                    <td>
                        <a href="{{ route('bills.show', $b) }}" class="fw-bold font-monospace text-decoration-none">
                            {{ $b->bill_number }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('contacts.show', $b->vendor) }}" class="text-dark fw-semibold text-decoration-none">
                            {{ $b->vendor->name }}
                        </a>
                    </td>
                    <td>{{ $b->bill_date->format('M d, Y') }}</td>
                    <td>
                        <span class="{{ $b->due_date->isPast() && !$b->isPaid() ? 'text-danger fw-semibold' : '' }}">
                            {{ $b->due_date->format('M d, Y') }}
                        </span>
                    </td>
                    <td class="num-col fw-bold">${{ number_format($b->total_amount, 2) }}</td>
                    <td class="num-col">${{ number_format($b->paid_amount, 2) }}</td>
                    <td class="num-col fw-bold {{ $b->remaining_amount > 0 ? 'text-danger' : 'text-success' }}">
                        ${{ number_format($b->remaining_amount, 2) }}
                    </td>
                    <td>
                        @if($b->status === 'paid')
                            <span class="badge bg-success">PAID</span>
                        @elseif($b->status === 'partially_paid')
                            <span class="badge bg-info text-dark">PARTIAL</span>
                        @elseif($b->status === 'approved')
                            <span class="badge bg-warning text-dark">APPROVED</span>
                        @else
                            <span class="badge bg-secondary text-uppercase">{{ $b->status }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('bills.show', $b) }}" class="btn btn-outline-primary btn-sm" title="View / Pay Bill">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No vendor bills recorded.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($bills->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $bills->links() }}
    </div>
    @endif
</div>
@endsection
