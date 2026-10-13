@extends('layouts.app')

@section('title', 'Payment ' . $payment->payment_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Payment Voucher: {{ $payment->payment_number }}</h3>
        <p class="text-muted mb-0 small">
            Type:
            @if($payment->type === 'received')
                <span class="badge bg-success bg-opacity-10 text-success border border-success">Customer Receipt</span>
            @else
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Vendor Disbursement</span>
            @endif
            &middot; Date: <span class="fw-semibold text-dark">{{ $payment->payment_date->format('F d, Y') }}</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Receipt Voucher
        </button>
        <a href="{{ route('payments.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Payments
        </a>
    </div>
</div>

<div class="card p-4 p-md-5 mb-4 shadow-sm border">
    <div class="row align-items-center mb-4 pb-4 border-bottom">
        <div class="col-sm-6">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-chart-pie text-primary fs-3"></i>
                <h4 class="fw-bold mb-0">ApexAIS Financials</h4>
            </div>
            <div class="text-muted small">Official Transaction Receipt & Voucher</div>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <h2 class="fw-bold mb-1 {{ $payment->type === 'received' ? 'text-success' : 'text-danger' }}">
                {{ $payment->type === 'received' ? 'RECEIPT' : 'DISBURSEMENT' }}
            </h2>
            <div class="font-monospace fw-bold fs-5">{{ $payment->payment_number }}</div>
            <div class="small text-muted mt-1">Transaction Date: {{ $payment->payment_date->format('F d, Y') }}</div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-sm-6">
            <div class="p-3 bg-light rounded h-100">
                <h6 class="text-muted small fw-bold text-uppercase mb-2">Party Details:</h6>
                <h5 class="fw-bold mb-1">{{ $payment->contact->name }}</h5>
                <div class="text-muted small">
                    {{ $payment->contact->company_name ?: '' }}<br>
                    {{ $payment->contact->address ?: 'No address specified' }}<br>
                    {{ $payment->contact->email ?: '' }} &middot; {{ $payment->contact->phone ?: '' }}
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="p-3 bg-light rounded h-100">
                <h6 class="text-muted small fw-bold text-uppercase mb-2">Treasury Details:</h6>
                <div>Bank Account: <strong>{{ $payment->bankAccount->code }} - {{ $payment->bankAccount->name }}</strong></div>
                <div>Payment Method: <strong>{{ $payment->payment_method }}</strong></div>
                <div>Reference ID: <strong>{{ $payment->reference ?: 'None' }}</strong></div>
                <div>Recorded By: <strong>{{ $payment->creator->name }}</strong></div>
            </div>
        </div>
    </div>

    <!-- Amount Highlight Box -->
    <div class="p-4 rounded border mb-4 text-center {{ $payment->type === 'received' ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger' }}">
        <div class="text-muted small text-uppercase fw-bold">Confirmed Transacted Amount</div>
        <h1 class="display-5 fw-bold mb-0 {{ $payment->type === 'received' ? 'text-success' : 'text-danger' }}">
            ${{ number_format($payment->amount, 2) }}
        </h1>
    </div>

    <!-- Allocation info -->
    @if($payment->invoice || $payment->bill)
    <div class="card mb-4">
        <div class="card-header bg-white fw-bold">Document Allocation</div>
        <div class="card-body">
            @if($payment->invoice)
                <div>Applied to Sales Invoice: <a href="{{ route('invoices.show', $payment->invoice) }}" class="fw-bold">{{ $payment->invoice->invoice_number }}</a> (Total: ${{ number_format($payment->invoice->total_amount, 2) }})</div>
            @endif
            @if($payment->bill)
                <div>Applied to Purchase Bill: <a href="{{ route('bills.show', $payment->bill) }}" class="fw-bold">{{ $payment->bill->bill_number }}</a> (Total: ${{ number_format($payment->bill->total_amount, 2) }})</div>
            @endif
        </div>
    </div>
    @endif

    @if($payment->journalEntry)
    <div class="card">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-link me-2 text-primary"></i>General Ledger Voucher: {{ $payment->journalEntry->entry_number }}</span>
            <a href="{{ route('journal-entries.show', $payment->journalEntry) }}" class="btn btn-sm btn-outline-primary">Open Journal</a>
        </div>
        <div class="table-responsive">
            <table class="table table-finance mb-0">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Type</th>
                        <th class="num-col">Debit ($)</th>
                        <th class="num-col">Credit ($)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payment->journalEntry->items as $item)
                    <tr>
                        <td>{{ $item->account->code }} - {{ $item->account->name }}</td>
                        <td><span class="badge bg-light text-dark border text-uppercase">{{ $item->account->type }}</span></td>
                        <td class="num-col">{{ $item->type === 'debit' ? '$' . number_format($item->amount, 2) : '-' }}</td>
                        <td class="num-col">{{ $item->type === 'credit' ? '$' . number_format($item->amount, 2) : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
