@extends('layouts.app')

@section('title', 'Database Backups')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Database Backups & Disaster Recovery</h3>
        <p class="text-muted mb-0 small">Create, download, and manage snapshot archives of your General Ledger and accounting database</p>
    </div>
    <form action="{{ route('backups.store') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-cloud-arrow-down me-1"></i> Generate Backup Now
        </button>
    </form>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3">
            <i class="fa-solid fa-shield-halved fs-3 text-info"></i>
            <div>
                <strong class="d-block">Disaster Recovery Best Practices</strong>
                <span class="small">Regularly download database backup archives and store them in secure, off-site, or secondary cold storage. In case of hardware failure or server migration, these archives restore your entire double-entry ledger.</span>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fa-solid fa-box-archive me-2 text-primary"></i> Stored Backup Archives ({{ count($backups) }})</span>
        <span class="badge bg-secondary text-uppercase">{{ config('database.default') }} ENGINE</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th>Archive Filename</th>
                    <th class="num-col">File Size</th>
                    <th>Created Timestamp</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $b)
                <tr>
                    <td class="font-monospace fw-bold text-dark">
                        <i class="fa-regular fa-file-zipper me-2 text-warning"></i>
                        {{ $b['filename'] }}
                    </td>
                    <td class="num-col font-monospace">{{ $b['size'] }} KB</td>
                    <td>{{ $b['created_at'] }}</td>
                    <td class="text-end">
                        <a href="{{ route('backups.download', $b['filename']) }}" class="btn btn-outline-success btn-sm me-1" title="Download Backup Archive">
                            <i class="fa-solid fa-download me-1"></i> Download
                        </a>
                        <form action="{{ route('backups.destroy', $b['filename']) }}" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete {{ $b['filename'] }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Archive">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">No database backup archives created yet. Click "Generate Backup Now" to create your first archive.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
