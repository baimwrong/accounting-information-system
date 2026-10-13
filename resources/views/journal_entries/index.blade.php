@extends('layouts.app')

@section('title', 'Journal Entries')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">General Journal Entries</h3>
        <p class="text-muted mb-0 small">Master double-entry transaction record connecting accounts to the General Ledger</p>
    </div>
    @if(auth()->user()->isAccountant())
    <a href="{{ route('journal-entries.create') }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i> New Journal Entry
    </a>
    @endif
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('journal-entries.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Status</span>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>Posted</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="voided" {{ request('status') === 'voided' ? 'selected' : '' }}>Voided</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">From</span>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">To</span>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Entry # / Ref / Desc" value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Entries Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 150px;">Entry #</th>
                    <th style="width: 110px;">Date</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>Source</th>
                    <th class="num-col" style="width: 140px;">Debit</th>
                    <th class="num-col" style="width: 140px;">Credit</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $je)
                <tr>
                    <td>
                        <a href="{{ route('journal-entries.show', $je) }}" class="fw-bold text-decoration-none font-monospace">
                            {{ $je->entry_number }}
                        </a>
                    </td>
                    <td>{{ $je->date->format('M d, Y') }}</td>
                    <td><span class="text-muted small">{{ $je->reference ?: '-' }}</span></td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 250px;">
                            {{ $je->description }}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border text-capitalize small">
                            {{ str_replace('_', ' ', $je->source_type) }}
                        </span>
                    </td>
                    <td class="num-col fw-bold">${{ number_format($je->total_debit, 2) }}</td>
                    <td class="num-col fw-bold">${{ number_format($je->total_credit, 2) }}</td>
                    <td>
                        @if($je->status === 'posted')
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">POSTED</span>
                        @elseif($je->status === 'draft')
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">DRAFT</span>
                        @else
                            <span class="badge bg-secondary">VOIDED</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('journal-entries.show', $je) }}" class="btn btn-outline-primary btn-sm" title="View Voucher">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No journal entries found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($entries->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $entries->links() }}
    </div>
    @endif
</div>
@endsection
