@extends('layouts.app')

@section('title', 'Fiscal Periods')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Fiscal Periods & Accounting Lock Dates</h3>
        <p class="text-muted mb-0 small">Control accounting periods, financial year-end closures, and historical entry locking</p>
    </div>
    @if(auth()->user()->isAdmin())
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newFiscalYearModal">
        <i class="fa-solid fa-plus me-1"></i> Open New Fiscal Year
    </button>
    @endif
</div>

<div class="card shadow-sm border">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th>Fiscal Year Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Closed By</th>
                    <th>Closed Timestamp</th>
                    <th class="text-end" style="width: 140px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fiscalYears as $fy)
                <tr>
                    <td class="fw-bold fs-6">{{ $fy->name }}</td>
                    <td>{{ $fy->start_date->format('M d, Y') }}</td>
                    <td>{{ $fy->end_date->format('M d, Y') }}</td>
                    <td>
                        @if($fy->status === 'active')
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">OPEN / ACTIVE</span>
                        @else
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">CLOSED & LOCKED</span>
                        @endif
                    </td>
                    <td>{{ $fy->closedByUser ? $fy->closedByUser->name : '-' }}</td>
                    <td>{{ $fy->closed_at ? $fy->closed_at->format('M d, Y H:i') : '-' }}</td>
                    <td class="text-end">
                        @if($fy->status === 'active' && auth()->user()->isAdmin())
                        <form action="{{ route('fiscal-years.close', $fy) }}" method="POST" onsubmit="return confirm('Closing {{ $fy->name }} will permanently lock this period from further manual entries or adjustments. Proceed?');" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Close and Lock Period">
                                <i class="fa-solid fa-lock me-1"></i> Close Period
                            </button>
                        </form>
                        @else
                        <span class="text-muted small"><i class="fa-solid fa-shield-halved text-success me-1"></i> Locked</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No fiscal years defined.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- New Fiscal Year Modal -->
@if(auth()->user()->isAdmin())
<div class="modal fade" id="newFiscalYearModal" tabindex="-1" aria-labelledby="newFiscalYearModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('fiscal-years.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newFiscalYearModalLabel"><i class="fa-solid fa-calendar-plus text-primary me-2"></i>Open New Fiscal Period</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Fiscal Period Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. FY 2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Period Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="{{ date('Y-01-01') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Period End Date <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" class="form-control" value="{{ date('Y-12-31') }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Open Fiscal Period</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
