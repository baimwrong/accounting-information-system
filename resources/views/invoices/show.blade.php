@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Sales Invoice: {{ $invoice->invoice_number }}</h3>
        <p class="text-muted mb-0 small">
            Status:
            @if($invoice->status === 'paid')
                <span class="badge bg-success">PAID IN FULL</span>
            @elseif($invoice->status === 'partially_paid')
                <span class="badge bg-info text-dark">PARTIALLY PAID</span>
            @elseif($invoice->status === 'sent')
                <span class="badge bg-primary">AWAITING PAYMENT</span>
            @else
                <span class="badge bg-secondary text-uppercase">{{ $invoice->status }}</span>
            @endif
            &middot; Customer: <span class="fw-semibold text-dark">{{ $invoice->customer->name }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Invoice
        </button>
        @if(!$invoice->isPaid() && auth()->user()->isAccountant())
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
            <i class="fa-solid fa-money-bill-wave me-1"></i> Record Payment
        </button>
        @endif
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Invoices
        </a>
    </div>
</div>

<!-- Invoice Document Sheet -->
<div class="card p-4 p-md-5 mb-4 shadow-sm border">
    <!-- Invoice Header -->
    <div class="row align-items-center mb-4 pb-4 border-bottom">
        <div class="col-sm-6">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-chart-pie text-primary fs-3"></i>
                <h4 class="fw-bold mb-0">ApexAIS Financials</h4>
            </div>
            <div class="text-muted small">
                Enterprise Accounting System<br>
                Tax / Registry ID: AIS-CORP-2026-991<br>
                accounting@internal.network
            </div>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <h2 class="fw-bold text-primary mb-1">INVOICE</h2>
            <div class="font-monospace fw-bold">{{ $invoice->invoice_number }}</div>
            <div class="small text-muted mt-2">
                <div>Issue Date: <strong>{{ $invoice->issue_date->format('F d, Y') }}</strong></div>
                <div>Payment Due: <strong>{{ $invoice->due_date->format('F d, Y') }}</strong></div>
            </div>
        </div>
    </div>

    <!-- Bill To Customer Info -->
    <div class="row mb-4">
        <div class="col-sm-6">
            <h6 class="text-muted small fw-bold text-uppercase mb-2">Billed To (Customer):</h6>
            <h5 class="fw-bold mb-1">{{ $invoice->customer->name }}</h5>
            @if($invoice->customer->company_name)
                <div class="fw-semibold text-muted">{{ $invoice->customer->company_name }}</div>
            @endif
            <div class="text-muted small">
                {{ $invoice->customer->address ?: 'No registered address' }}<br>
                {{ $invoice->customer->email ?: '' }} &middot; {{ $invoice->customer->phone ?: '' }}<br>
                @if($invoice->customer->tax_number)
                    Tax ID: {{ $invoice->customer->tax_number }}
                @endif
            </div>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            @if($invoice->journalEntry)
            <div class="p-3 bg-light rounded text-start d-inline-block border">
                <div class="small text-muted fw-bold text-uppercase mb-1"><i class="fa-solid fa-link me-1"></i> General Ledger Link</div>
                <div class="small">
                    Voucher:
                    <a href="{{ route('journal-entries.show', $invoice->journalEntry) }}" class="fw-bold text-decoration-none">
                        {{ $invoice->journalEntry->entry_number }}
                    </a>
                </div>
                <div class="small text-muted">Status: <span class="badge bg-success bg-opacity-10 text-success border border-success">POSTED</span></div>
            </div>
            @endif
        </div>
    </div>

    <!-- Invoice Line Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Item & Description</th>
                    <th>GL Revenue Account</th>
                    <th class="num-col" style="width: 80px;">Qty</th>
                    <th class="num-col" style="width: 130px;">Unit Price</th>
                    <th class="num-col" style="width: 90px;">Tax</th>
                    <th class="num-col" style="width: 140px;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $idx => $item)
                <tr>
                    <td class="text-muted small text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="fw-semibold">{{ $item->description }}</div>
                        @if($item->product)
                            <div class="small text-muted">Code: {{ $item->product->code }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="small font-monospace">{{ $item->account->code }}</span> - {{ $item->account->name }}
                    </td>
                    <td class="num-col">{{ number_format($item->quantity, 0) }}</td>
                    <td class="num-col">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="num-col">{{ number_format($item->tax_percent, 1) }}%</td>
                    <td class="num-col fw-bold">${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Totals summary box -->
    <div class="row justify-content-end mb-4">
        <div class="col-sm-5">
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="text-muted">Subtotal:</td>
                    <td class="num-col fw-semibold">${{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->discount_total > 0)
                <tr>
                    <td class="text-muted">Discount Total:</td>
                    <td class="num-col text-danger">-${{ number_format($invoice->discount_total, 2) }}</td>
                </tr>
                @endif
                @if($invoice->tax_total > 0)
                <tr>
                    <td class="text-muted">Sales Tax:</td>
                    <td class="num-col fw-semibold">${{ number_format($invoice->tax_total, 2) }}</td>
                </tr>
                @endif
                <tr class="border-top">
                    <td class="fw-bold fs-5">Invoice Total:</td>
                    <td class="num-col fw-bold fs-5 text-primary">${{ number_format($invoice->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Amount Paid:</td>
                    <td class="num-col text-success fw-semibold">${{ number_format($invoice->paid_amount, 2) }}</td>
                </tr>
                <tr class="border-top table-light">
                    <td class="fw-bold">Balance Due:</td>
                    <td class="num-col fw-bold text-danger fs-5">${{ number_format($invoice->remaining_amount, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    @if($invoice->notes)
    <div class="p-3 bg-light rounded mb-3">
        <h6 class="small fw-bold text-muted text-uppercase mb-1">Notes & Terms:</h6>
        <div class="small">{{ $invoice->notes }}</div>
    </div>
    @endif
</div>

<!-- Payments Received History -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-money-bill-transfer text-success me-2"></i>Payments Received Against This Invoice</h6>
        @if(!$invoice->isPaid() && auth()->user()->isAccountant())
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
            <i class="fa-solid fa-plus me-1"></i> Record Payment
        </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-finance mb-0">
            <thead>
                <tr>
                    <th>Payment #</th>
                    <th>Date</th>
                    <th>Bank / Cash Account</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="num-col">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->payments as $pay)
                <tr>
                    <td>
                        <a href="{{ route('payments.show', $pay) }}" class="fw-semibold text-decoration-none font-monospace">
                            {{ $pay->payment_number }}
                        </a>
                    </td>
                    <td>{{ $pay->payment_date->format('M d, Y') }}</td>
                    <td>{{ $pay->bankAccount->code }} - {{ $pay->bankAccount->name }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $pay->payment_method }}</span></td>
                    <td>{{ $pay->reference ?: '-' }}</td>
                    <td class="num-col fw-bold text-success">${{ number_format($pay->amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">No payments recorded yet for this invoice.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Record Payment Modal -->
@if(!$invoice->isPaid() && auth()->user()->isAccountant())
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('invoices.payments.store', $invoice) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="recordPaymentModalLabel"><i class="fa-solid fa-money-bill-wave text-success me-2"></i>Record Customer Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border p-2 small mb-3">
                        Total Invoice: <strong>${{ number_format($invoice->total_amount, 2) }}</strong> &middot;
                        Remaining Balance: <strong class="text-danger">${{ number_format($invoice->remaining_amount, 2) }}</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Payment Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ $invoice->remaining_amount }}" name="amount" class="form-control fw-bold" value="{{ $invoice->remaining_amount }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Deposit To Bank / Cash Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" class="form-select" required>
                            @foreach($bankAccounts as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->code }} - {{ $bank->name }} (${{ number_format($bank->balance, 2) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="Bank Transfer">Bank Transfer / Wire</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reference / Transaction ID</label>
                        <input type="text" name="reference" class="form-control" placeholder="e.g. Wire confirmation #, Check #">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Payment Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-semibold">
                        <i class="fa-solid fa-check me-1"></i> Confirm & Post Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
