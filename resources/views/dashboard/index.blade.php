@extends('layouts.app')

@section('title', 'Financial Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Financial Executive Dashboard</h3>
        <p class="text-muted mb-0 small">Real-time overview of General Ledger, Receivables, Payables, and Cash Flow</p>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->isAccountant())
        <a href="{{ route('journal-entries.create') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i> New Journal Entry
        </a>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Create Invoice
        </a>
        @endif
        @if(auth()->user()->isAdmin())
        <a href="{{ route('imports.index') }}" class="btn btn-warning btn-sm text-dark">
            <i class="fa-solid fa-file-arrow-up me-1"></i> Bulk Import
        </a>
        @endif
    </div>
</div>

<!-- Financial KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Cash & Bank -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted small fw-semibold text-uppercase">Total Cash & Bank</span>
                <span class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle">
                    <i class="fa-solid fa-vault fs-5"></i>
                </span>
            </div>
            <h4 class="fw-bold mb-1 num-col text-start">${{ number_format($totalCashBank, 2) }}</h4>
            <div class="small text-muted">Operating Liquidity</div>
        </div>
    </div>

    <!-- Accounts Receivable -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted small fw-semibold text-uppercase">Receivables (AR)</span>
                <span class="p-2 bg-success bg-opacity-10 text-success rounded-circle">
                    <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                </span>
            </div>
            <h4 class="fw-bold mb-1 num-col text-start text-success">${{ number_format($totalAR, 2) }}</h4>
            <div class="small text-muted">{{ $unpaidInvoicesCount }} unpaid customer invoices</div>
        </div>
    </div>

    <!-- Accounts Payable -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 p-3 border-start border-4 border-danger">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted small fw-semibold text-uppercase">Payables (AP)</span>
                <span class="p-2 bg-danger bg-opacity-10 text-danger rounded-circle">
                    <i class="fa-solid fa-file-invoice fs-5"></i>
                </span>
            </div>
            <h4 class="fw-bold mb-1 num-col text-start text-danger">${{ number_format($totalAP, 2) }}</h4>
            <div class="small text-muted">{{ $unpaidBillsCount }} unpaid vendor bills</div>
        </div>
    </div>

    <!-- Net Profit YTD -->
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100 p-3 border-start border-4 {{ $ytdNetProfit >= 0 ? 'border-info' : 'border-warning' }}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="text-muted small fw-semibold text-uppercase">Net Profit (YTD)</span>
                <span class="p-2 bg-info bg-opacity-10 text-info rounded-circle">
                    <i class="fa-solid fa-chart-line fs-5"></i>
                </span>
            </div>
            <h4 class="fw-bold mb-1 num-col text-start {{ $ytdNetProfit >= 0 ? 'text-primary' : 'text-danger' }}">
                ${{ number_format($ytdNetProfit, 2) }}
            </h4>
            <div class="small text-muted">Month Revenue: ${{ number_format($monthRevenue, 2) }}</div>
        </div>
    </div>
</div>

<!-- Alerts / Pending Actions -->
@if($draftJournalsCount > 0 || $unpaidInvoicesCount > 0 || $unpaidBillsCount > 0)
<div class="alert alert-light border shadow-sm p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-bell text-warning fs-5"></i>
        <span class="fw-semibold">Attention Required:</span>
        <div class="d-flex flex-wrap gap-2">
            @if($draftJournalsCount > 0)
                <a href="{{ route('journal-entries.index', ['status' => 'draft']) }}" class="badge bg-warning text-dark text-decoration-none">
                    {{ $draftJournalsCount }} Draft Journals
                </a>
            @endif
            @if($unpaidInvoicesCount > 0)
                <a href="{{ route('invoices.index', ['status' => 'sent']) }}" class="badge bg-danger text-white text-decoration-none">
                    {{ $unpaidInvoicesCount }} Uncollected Invoices
                </a>
            @endif
            @if($unpaidBillsCount > 0)
                <a href="{{ route('bills.index', ['status' => 'approved']) }}" class="badge bg-secondary text-white text-decoration-none">
                    {{ $unpaidBillsCount }} Open Bills
                </a>
            @endif
        </div>
    </div>
    <div class="small text-muted">
        <i class="fa-solid fa-clock me-1"></i> Period: {{ date('F Y') }}
    </div>
</div>
@endif

<!-- Revenue vs Expense 6-Month Comparison (Offline SVG Graphic) -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-chart-simple text-primary"></i>
            <span class="fw-bold">6-Month Revenue & Expense Trend</span>
        </div>
        <div class="d-flex align-items-center gap-3 small">
            <span><span class="badge bg-primary me-1">&nbsp;</span> Revenue</span>
            <span><span class="badge bg-danger me-1">&nbsp;</span> Total Expenses</span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm text-center align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-start">Metric</th>
                        @foreach($trendMonths as $m)
                            <th>{{ $m }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-start fw-semibold text-primary"><i class="fa-solid fa-circle-arrow-up me-1"></i> Revenue</td>
                        @foreach($trendRevenues as $rev)
                            <td class="num-col">${{ number_format($rev, 2) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="text-start fw-semibold text-danger"><i class="fa-solid fa-circle-arrow-down me-1"></i> Expenses</td>
                        @foreach($trendExpenses as $exp)
                            <td class="num-col">${{ number_format($exp, 2) }}</td>
                        @endforeach
                    </tr>
                    <tr class="table-light">
                        <td class="text-start fw-bold">Net Margin</td>
                        @foreach($trendMonths as $idx => $m)
                            @php $net = $trendRevenues[$idx] - $trendExpenses[$idx]; @endphp
                            <td class="num-col fw-bold {{ $net >= 0 ? 'text-success' : 'text-danger' }}">
                                ${{ number_format($net, 2) }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Transactions Tables -->
<div class="row g-4">
    <!-- Recent Journal Entries -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="fw-bold"><i class="fa-solid fa-book-journal-whills me-2 text-primary"></i>Recent Journal Entries</span>
                <a href="{{ route('journal-entries.index') }}" class="btn btn-sm btn-link p-0 text-decoration-none">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Entry #</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="num-col">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentJournals as $je)
                        <tr>
                            <td>
                                <a href="{{ route('journal-entries.show', $je) }}" class="fw-semibold text-decoration-none">
                                    {{ $je->entry_number }}
                                </a>
                            </td>
                            <td>{{ $je->date->format('M d') }}</td>
                            <td><span class="text-truncate d-inline-block" style="max-width: 140px;">{{ $je->description }}</span></td>
                            <td class="num-col">${{ number_format($je->total_debit, 2) }}</td>
                            <td>
                                @if($je->status === 'posted')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">POSTED</span>
                                @elseif($je->status === 'draft')
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">DRAFT</span>
                                @else
                                    <span class="badge bg-secondary">VOIDED</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">No recent journal entries.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Sales Invoices -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <span class="fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i>Recent Sales Invoices</span>
                <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-link p-0 text-decoration-none">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th class="num-col">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentInvoices as $inv)
                        <tr>
                            <td>
                                <a href="{{ route('invoices.show', $inv) }}" class="fw-semibold text-decoration-none">
                                    {{ $inv->invoice_number }}
                                </a>
                            </td>
                            <td>{{ $inv->customer->name }}</td>
                            <td>{{ $inv->issue_date->format('M d') }}</td>
                            <td class="num-col">${{ number_format($inv->total_amount, 2) }}</td>
                            <td>
                                @if($inv->status === 'paid')
                                    <span class="badge bg-success">PAID</span>
                                @elseif($inv->status === 'partially_paid')
                                    <span class="badge bg-info text-dark">PARTIAL</span>
                                @elseif($inv->status === 'sent')
                                    <span class="badge bg-primary">SENT</span>
                                @else
                                    <span class="badge bg-secondary">{{ strtoupper($inv->status) }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">No sales invoices found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
