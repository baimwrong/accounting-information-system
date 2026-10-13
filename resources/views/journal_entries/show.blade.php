@extends('layouts.app')

@section('title', 'Journal Entry ' . $journalEntry->entry_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Journal Entry Voucher: {{ $journalEntry->entry_number }}</h3>
        <p class="text-muted mb-0 small">
            Date: <span class="fw-semibold text-dark">{{ $journalEntry->date->format('F d, Y') }}</span> &middot;
            Source: <span class="badge bg-light text-dark border text-capitalize">{{ str_replace('_', ' ', $journalEntry->source_type) }}</span> &middot;
            Status:
            @if($journalEntry->status === 'posted')
                <span class="badge bg-success bg-opacity-10 text-success border border-success">POSTED</span>
            @elseif($journalEntry->status === 'draft')
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">DRAFT</span>
            @else
                <span class="badge bg-secondary">VOIDED</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print();">
            <i class="fa-solid fa-print me-1"></i> Print Voucher
        </button>

        @if(auth()->user()->isAccountant())
            @if($journalEntry->status === 'draft')
            <form action="{{ route('journal-entries.post', $journalEntry) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-check-double me-1"></i> Post to General Ledger
                </button>
            </form>
            @endif

            @if($journalEntry->status === 'posted')
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#voidModal">
                <i class="fa-solid fa-ban me-1"></i> Void Entry
            </button>
            @endif
        @endif

        <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Entries
        </a>
    </div>
</div>

<!-- Details Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-4">
                <div class="text-muted small">Reference</div>
                <div class="fw-semibold">{{ $journalEntry->reference ?: 'None' }}</div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted small">Created By</div>
                <div class="fw-semibold">{{ $journalEntry->creator->name }} <span class="text-muted small">({{ $journalEntry->created_at->format('M d, Y H:i') }})</span></div>
            </div>
            <div class="col-sm-4">
                <div class="text-muted small">Posted By</div>
                <div class="fw-semibold">
                    {{ $journalEntry->poster ? $journalEntry->poster->name : 'Not yet posted' }}
                    @if($journalEntry->posted_at)
                        <span class="text-muted small">({{ $journalEntry->posted_at->format('M d, Y H:i') }})</span>
                    @endif
                </div>
            </div>
            <div class="col-12">
                <div class="text-muted small">Description / Transaction Narrative</div>
                <div class="p-2 bg-light rounded">{{ $journalEntry->description }}</div>
            </div>
        </div>
    </div>
</div>

<!-- Double-Entry Line Items -->
<div class="card">
    <div class="card-header bg-white">
        <h6 class="fw-bold mb-0">General Ledger Distribution</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">Account Code</th>
                    <th>Account Title</th>
                    <th>Line Memo</th>
                    <th class="num-col" style="width: 160px;">Debit ($)</th>
                    <th class="num-col" style="width: 160px;">Credit ($)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($journalEntry->items as $item)
                <tr>
                    <td class="font-monospace fw-bold text-primary">
                        <a href="{{ route('accounts.statement', $item->account) }}" class="text-decoration-none">
                            {{ $item->account->code }}
                        </a>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $item->account->name }}</div>
                        <div class="small text-muted">{{ ucfirst($item->account->type) }}</div>
                    </td>
                    <td>{{ $item->memo ?: '-' }}</td>
                    <td class="num-col {{ $item->type === 'debit' ? 'fw-bold text-dark' : 'text-muted' }}">
                        {{ $item->type === 'debit' ? '$' . number_format($item->amount, 2) : '-' }}
                    </td>
                    <td class="num-col {{ $item->type === 'credit' ? 'fw-bold text-dark' : 'text-muted' }}">
                        {{ $item->type === 'credit' ? '$' . number_format($item->amount, 2) : '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="3" class="text-end">Balance Verification Total:</td>
                    <td class="num-col">${{ number_format($journalEntry->total_debit, 2) }}</td>
                    <td class="num-col">${{ number_format($journalEntry->total_credit, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Void Modal -->
@if($journalEntry->status === 'posted')
<div class="modal fade" id="voidModal" tabindex="-1" aria-labelledby="voidModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('journal-entries.void', $journalEntry) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger" id="voidModalLabel"><i class="fa-solid fa-triangle-exclamation me-2"></i>Void Journal Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Voiding will nullify this entry from future balance rollups and mark it as voided. This action is permanently recorded in the audit trail.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reason for Voiding <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Specify why this entry is being voided..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-semibold">Confirm Void Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
