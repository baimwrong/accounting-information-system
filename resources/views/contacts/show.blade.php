@extends('layouts.app')

@section('title', 'Contact - ' . $contact->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">{{ $contact->name }}</h3>
        <p class="text-muted mb-0 small">
            {{ $contact->company_name ?: 'Individual Contact' }} &middot;
            Type: <span class="badge bg-light text-dark border text-uppercase">{{ $contact->type }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('contacts.edit', $contact) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Details
        </a>
        <a href="{{ route('contacts.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Directory
        </a>
    </div>
</div>

<!-- Contact Info & Balance -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card h-100 p-4">
            <div class="row g-3">
                <div class="col-sm-6">
                    <span class="text-muted small">Email Address</span>
                    <div class="fw-semibold">{{ $contact->email ?: 'N/A' }}</div>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small">Phone Number</span>
                    <div class="fw-semibold">{{ $contact->phone ?: 'N/A' }}</div>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small">Tax / VAT Number</span>
                    <div class="fw-semibold">{{ $contact->tax_number ?: 'N/A' }}</div>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small">Credit Limit</span>
                    <div class="fw-semibold">{{ $contact->credit_limit ? '$' . number_format($contact->credit_limit, 2) : 'Unrestricted' }}</div>
                </div>
                <div class="col-12">
                    <span class="text-muted small">Billing / Shipping Address</span>
                    <div class="fw-normal">{{ $contact->address ?: 'No address provided' }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 p-4 border-start border-4 border-primary d-flex flex-column justify-content-center">
            <span class="text-muted small text-uppercase fw-semibold">Net Outstanding Balance</span>
            <h2 class="fw-bold my-2 text-primary num-col text-start">${{ number_format($contact->outstanding_balance, 2) }}</h2>
            <div class="small text-muted">
                Status:
                @if($contact->is_active)
                    <span class="badge bg-success-subtle text-success">Active Account</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Invoices / Bills Tabs -->
<div class="card">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="contactTabs" role="tablist">
            @if($contact->type === 'customer' || $contact->type === 'both')
            <li class="nav-item">
                <button class="nav-link active" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices-pane" type="button" role="tab">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i> Sales Invoices ({{ $contact->invoices->count() }})
                </button>
            </li>
            @endif
            @if($contact->type === 'vendor' || $contact->type === 'both')
            <li class="nav-item">
                <button class="nav-link {{ $contact->type === 'vendor' ? 'active' : '' }}" id="bills-tab" data-bs-toggle="tab" data-bs-target="#bills-pane" type="button" role="tab">
                    <i class="fa-solid fa-receipt me-1"></i> Purchase Bills ({{ $contact->bills->count() }})
                </button>
            </li>
            @endif
            <li class="nav-item">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments-pane" type="button" role="tab">
                    <i class="fa-solid fa-money-bill-transfer me-1"></i> Payments ({{ $contact->payments->count() }})
                </button>
            </li>
        </ul>
    </div>
    <div class="tab-content p-3" id="contactTabsContent">
        @if($contact->type === 'customer' || $contact->type === 'both')
        <div class="tab-pane fade show active" id="invoices-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th class="num-col">Total</th>
                            <th class="num-col">Paid</th>
                            <th class="num-col">Balance Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contact->invoices as $inv)
                        <tr>
                            <td><a href="{{ route('invoices.show', $inv) }}" class="fw-semibold text-decoration-none">{{ $inv->invoice_number }}</a></td>
                            <td>{{ $inv->issue_date->format('M d, Y') }}</td>
                            <td>{{ $inv->due_date->format('M d, Y') }}</td>
                            <td class="num-col">${{ number_format($inv->total_amount, 2) }}</td>
                            <td class="num-col">${{ number_format($inv->paid_amount, 2) }}</td>
                            <td class="num-col fw-bold text-danger">${{ number_format($inv->remaining_amount, 2) }}</td>
                            <td><span class="badge bg-secondary text-uppercase">{{ $inv->status }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No invoices on record.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($contact->type === 'vendor' || $contact->type === 'both')
        <div class="tab-pane fade {{ $contact->type === 'vendor' ? 'show active' : '' }}" id="bills-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th class="num-col">Total</th>
                            <th class="num-col">Paid</th>
                            <th class="num-col">Balance Due</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contact->bills as $b)
                        <tr>
                            <td><a href="{{ route('bills.show', $b) }}" class="fw-semibold text-decoration-none">{{ $b->bill_number }}</a></td>
                            <td>{{ $b->bill_date->format('M d, Y') }}</td>
                            <td>{{ $b->due_date->format('M d, Y') }}</td>
                            <td class="num-col">${{ number_format($b->total_amount, 2) }}</td>
                            <td class="num-col">${{ number_format($b->paid_amount, 2) }}</td>
                            <td class="num-col fw-bold text-danger">${{ number_format($b->remaining_amount, 2) }}</td>
                            <td><span class="badge bg-secondary text-uppercase">{{ $b->status }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No bills on record.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="tab-pane fade" id="payments-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Payment #</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="num-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contact->payments as $p)
                        <tr>
                            <td><a href="{{ route('payments.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->payment_number }}</a></td>
                            <td><span class="badge bg-light text-dark border text-uppercase">{{ $p->type }}</span></td>
                            <td>{{ $p->payment_date->format('M d, Y') }}</td>
                            <td>{{ $p->payment_method }}</td>
                            <td>{{ $p->reference ?: '-' }}</td>
                            <td class="num-col fw-bold text-success">${{ number_format($p->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No payments recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
