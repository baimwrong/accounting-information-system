@extends('layouts.app')

@section('title', 'Bill ' . $bill->bill_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Purchase Bill: {{ $bill->bill_number }}</h3>
        <p class="text-muted mb-0 small">
            Status:
            @if($bill->status === 'paid')
                <span class="badge bg-success">PAID IN FULL</span>
            @elseif($bill->status === 'partially_paid')
                <span class="badge bg-info text-dark">PARTIALLY PAID</span>
            @elseif($bill->status === 'approved')
                <span class="badge bg-warning text-dark">APPROVED / UNPAID</span>
            @else
                <span class="badge bg-secondary text-uppercase">{{ $bill->status }}</span>
            @endif
            &middot; Vendor: <span class="fw-semibold text-dark">{{ $bill->vendor->name }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Bill
        </button>
        @if(!$bill->isPaid() && auth()->user()->isAccountant())
        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Pay Bill
        </button>
        @endif
        <a href="{{ route('bills.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Bills
        </a>
    </div>
</div>

<!-- Bill Document Sheet -->
<div class="card p-4 p-md-5 mb-4 shadow-sm border">
    <div class="row align-items-center mb-4 pb-4 border-bottom">
        <div class="col-sm-6">
            <h6 class="text-muted small fw-bold text-uppercase mb-2">Vendor / Supplier:</h6>
            <h4 class="fw-bold mb-1">{{ $bill->vendor->name }}</h4>
            @if($bill->vendor->company_name)
                <div class="fw-semibold text-muted">{{ $bill->vendor->company_name }}</div>
            @endif
            <div class="text-muted small">
                {{ $bill->vendor->address ?: 'No address specified' }}<br>
                {{ $bill->vendor->email ?: '' }} &middot; {{ $bill->vendor->phone ?: '' }}<br>
                @if($bill->vendor->tax_number)
                    Tax ID: {{ $bill->vendor->tax_number }}
                @endif
            </div>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <h2 class="fw-bold text-danger mb-1">PURCHASE BILL</h2>
            <div class="font-monospace fw-bold">{{ $bill->bill_number }}</div>
            <div class="small text-muted mt-2">
                <div>Bill Date: <strong>{{ $bill->bill_date->format('F d, Y') }}</strong></div>
                <div>Payment Due: <strong>{{ $bill->due_date->format('F d, Y') }}</strong></div>
            </div>
            @if($bill->journalEntry)
            <div class="mt-3">
                <a href="{{ route('journal-entries.show', $bill->journalEntry) }}" class="badge bg-light text-dark border text-decoration-none p-2">
                    <i class="fa-solid fa-link me-1"></i> GL Voucher: {{ $bill->journalEntry->entry_number }}
                </a>
            </div>
            @endif
        </div>
    </div>

    <!-- Bill Line Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Expense / Item Description</th>
                    <th>GL Expense Account</th>
                    <th class="num-col" style="width: 80px;">Qty</th>
                    <th class="num-col" style="width: 130px;">Cost Price</th>
                    <th class="num-col" style="width: 90px;">Tax</th>
                    <th class="num-col" style="width: 140px;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bill->items as $idx => $item)
                <tr>
                    <td class="text-muted small text-center">{{ $idx + 1 }}</td>
                    <td>
                        <div class="fw-semibold">{{ $item->description }}</div>
                        @if($item->product)
                            <div class="small text-muted">Catalog SKU: {{ $item->product->code }}</div>
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
                    <td class="num-col fw-semibold">${{ number_format($bill->subtotal, 2) }}</td>
                </tr>
                @if($bill->tax_total > 0)
                <tr>
                    <td class="text-muted">Input Tax:</td>
                    <td class="num-col fw-semibold">${{ number_format($bill->tax_total, 2) }}</td>
                </tr>
                @endif
                <tr class="border-top">
                    <td class="fw-bold fs-5">Bill Total:</td>
                    <td class="num-col fw-bold fs-5 text-danger">${{ number_format($bill->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Disbursed Paid:</td>
                    <td class="num-col text-success fw-semibold">${{ number_format($bill->paid_amount, 2) }}</td>
                </tr>
                <tr class="border-top table-light">
                    <td class="fw-bold">Payable Due:</td>
                    <td class="num-col fw-bold text-danger fs-5">${{ number_format($bill->remaining_amount, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    @if($bill->notes)
    <div class="p-3 bg-light rounded">
        <h6 class="small fw-bold text-muted text-uppercase mb-1">Internal Notes:</h6>
        <div class="small">{{ $bill->notes }}</div>
    </div>
    @endif
</div>

<!-- Payments Made History -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-money-bill-transfer text-danger me-2"></i>Disbursement Payments Made For This Bill</h6>
        @if(!$bill->isPaid() && auth()->user()->isAccountant())
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
            <i class="fa-solid fa-plus me-1"></i> Make Payment
        </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-finance mb-0">
            <thead>
                <tr>
                    <th>Disbursement #</th>
                    <th>Date</th>
                    <th>Disbursing Bank Account</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="num-col">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bill->payments as $pay)
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
                    <td class="num-col fw-bold text-danger">${{ number_format($pay->amount, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">No disbursement payments recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Record Disbursement Modal -->
@if(!$bill->isPaid() && auth()->user()->isAccountant())
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('bills.payments.store', $bill) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="recordPaymentModalLabel"><i class="fa-solid fa-money-bill-transfer text-danger me-2"></i>Pay Vendor Bill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border p-2 small mb-3">
                        Total Bill: <strong>${{ number_format($bill->total_amount, 2) }}</strong> &middot;
                        Remaining Balance Due: <strong class="text-danger">${{ number_format($bill->remaining_amount, 2) }}</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Payment Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ $bill->remaining_amount }}" name="amount" class="form-control fw-bold" value="{{ $bill->remaining_amount }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Disburse From Bank / Cash Account <span class="text-danger">*</span></label>
                        <select name="bank_account_id" class="form-select" required>
                            @foreach($bankAccounts as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->code }} - {{ $bank->name }} (${{ number_format($bank->balance, 2) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Disbursement Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="Bank Transfer">Bank Transfer / Wire</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Corporate Card">Corporate Card</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Wire Confirmation # / Check #</label>
                        <input type="text" name="reference" class="form-control" placeholder="e.g. TR-998811">
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                        <i class="fa-solid fa-check me-1"></i> Disburse & Post Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
