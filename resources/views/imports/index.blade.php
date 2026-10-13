@extends('layouts.app')

@section('title', 'Admin Bulk Data Import')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-file-arrow-up text-warning me-2"></i>Admin Bulk Data Import Center</h3>
        <p class="text-muted mb-0 small">Import Chart of Accounts, Contacts, Products, and Multi-line General Journal Entries from CSV files</p>
    </div>
    <div>
        <span class="badge bg-warning text-dark border p-2">
            <i class="fa-solid fa-user-shield me-1"></i> Administrator Privileges Active
        </span>
    </div>
</div>

<!-- Import Forms Card -->
<div class="card mb-4 shadow-sm border">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="importTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="coa-tab" data-bs-toggle="tab" data-bs-target="#coa" type="button" role="tab">
                    <i class="fa-solid fa-sitemap text-primary me-2"></i>1. Chart of Accounts
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab">
                    <i class="fa-solid fa-users text-success me-2"></i>2. Contacts (Customers/Vendors)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                    <i class="fa-solid fa-boxes-stacked text-info me-2"></i>3. Products & Services
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="journals-tab" data-bs-toggle="tab" data-bs-target="#journals" type="button" role="tab">
                    <i class="fa-solid fa-book-journal-whills text-danger me-2"></i>4. Historical Journal Entries
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4">
        <div class="tab-content" id="importTabContent">
            <!-- 1. CHART OF ACCOUNTS -->
            <div class="tab-pane fade show active" id="coa" role="tabpanel">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <h5 class="fw-bold mb-2">Import Chart of Accounts from CSV</h5>
                        <p class="text-muted small mb-3">
                            Upload your master Chart of Accounts. The importer supports automatic classification into Assets, Liabilities, Equity, Revenue, and Expenses, with normal balance auto-detection and parent-child hierarchies.
                        </p>
                        <form action="{{ route('imports.accounts') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Choose CSV File (.csv)</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload & Process Accounts
                                </button>
                                <a href="{{ route('imports.template', 'chart_of_accounts') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-download me-1"></i> Download Sample CSV Template
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <div class="p-3 bg-light rounded border small">
                            <h6 class="fw-bold mb-2 text-primary"><i class="fa-solid fa-circle-info me-1"></i> Required Columns:</h6>
                            <ul class="mb-2 ps-3">
                                <li><code>code</code>: Account number (e.g. 1010, 2010)</li>
                                <li><code>name</code>: Account title</li>
                                <li><code>type</code>: <code>asset</code>, <code>liability</code>, <code>equity</code>, <code>revenue</code>, <code>expense</code></li>
                            </ul>
                            <h6 class="fw-bold mb-1 text-muted">Optional Columns:</h6>
                            <div class="text-muted"><code>sub_type</code>, <code>normal_balance</code>, <code>parent_code</code>, <code>description</code></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. CONTACTS -->
            <div class="tab-pane fade" id="contacts" role="tabpanel">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <h5 class="fw-bold mb-2">Import Customers & Vendors from CSV</h5>
                        <p class="text-muted small mb-3">
                            Bulk import client rosters, suppliers, credit terms, and initial opening balances.
                        </p>
                        <form action="{{ route('imports.contacts') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Choose CSV File (.csv)</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success btn-sm fw-semibold">
                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload & Process Contacts
                                </button>
                                <a href="{{ route('imports.template', 'contacts') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-download me-1"></i> Download Sample CSV Template
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <div class="p-3 bg-light rounded border small">
                            <h6 class="fw-bold mb-2 text-success"><i class="fa-solid fa-circle-info me-1"></i> Required Columns:</h6>
                            <ul class="mb-2 ps-3">
                                <li><code>type</code>: <code>customer</code>, <code>vendor</code>, or <code>both</code></li>
                                <li><code>name</code>: Primary person or business name</li>
                            </ul>
                            <h6 class="fw-bold mb-1 text-muted">Optional Columns:</h6>
                            <div class="text-muted"><code>company_name</code>, <code>email</code>, <code>phone</code>, <code>tax_number</code>, <code>address</code>, <code>credit_limit</code>, <code>opening_balance</code></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. PRODUCTS -->
            <div class="tab-pane fade" id="products" role="tabpanel">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <h5 class="fw-bold mb-2">Import Products & Services from CSV</h5>
                        <p class="text-muted small mb-3">
                            Bulk import catalog items, pricing models, stock counts, and their default General Ledger revenue and expense mappings.
                        </p>
                        <form action="{{ route('imports.products') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Choose CSV File (.csv)</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-info btn-sm fw-semibold text-white">
                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload & Process Products
                                </button>
                                <a href="{{ route('imports.template', 'products') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-download me-1"></i> Download Sample CSV Template
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <div class="p-3 bg-light rounded border small">
                            <h6 class="fw-bold mb-2 text-info"><i class="fa-solid fa-circle-info me-1"></i> Required Columns:</h6>
                            <ul class="mb-2 ps-3">
                                <li><code>code</code>: SKU or service code</li>
                                <li><code>name</code>: Item title</li>
                                <li><code>income_account_code</code>: Existing revenue account code</li>
                                <li><code>expense_account_code</code>: Existing expense account code</li>
                            </ul>
                            <h6 class="fw-bold mb-1 text-muted">Optional Columns:</h6>
                            <div class="text-muted"><code>type</code> (goods/service), <code>sale_price</code>, <code>purchase_price</code>, <code>tax_rate</code>, <code>quantity_on_hand</code>, <code>description</code></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. JOURNAL ENTRIES -->
            <div class="tab-pane fade" id="journals" role="tabpanel">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <h5 class="fw-bold mb-2">Import Multi-Row Journal Entries from CSV</h5>
                        <p class="text-muted small mb-3">
                            Import historical journal entries or opening account balances. Rows with identical <code>entry_number</code> are grouped into a single compound journal transaction. Every entry is validated for double-entry equilibrium (&Sigma; Debit = &Sigma; Credit) before posting!
                        </p>
                        <form action="{{ route('imports.journal-entries') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Choose CSV File (.csv)</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                                    <i class="fa-solid fa-cloud-arrow-up me-1"></i> Upload & Post Journal Entries
                                </button>
                                <a href="{{ route('imports.template', 'journal_entries') }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-download me-1"></i> Download Sample CSV Template
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <div class="p-3 bg-light rounded border small">
                            <h6 class="fw-bold mb-2 text-danger"><i class="fa-solid fa-circle-info me-1"></i> Multi-Line Transaction Format:</h6>
                            <ul class="mb-2 ps-3">
                                <li><code>entry_number</code>: Shared identifier for line pairing</li>
                                <li><code>date</code>: YYYY-MM-DD</li>
                                <li><code>account_code</code>: Target COA account code</li>
                                <li><code>debit</code>: Debit amount (or 0)</li>
                                <li><code>credit</code>: Credit amount (or 0)</li>
                            </ul>
                            <div class="alert alert-warning p-2 mb-0 small">
                                <strong>Rule:</strong> Sum of debits must equal sum of credits for each entry_number!
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Import History Log -->
<div class="card shadow-sm border">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Bulk Import Batch History</h6>
        <span class="text-muted small">Automatic audit log recording</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 80px;">Batch #</th>
                    <th>Import Type</th>
                    <th>File Name</th>
                    <th>Imported By</th>
                    <th>Timestamp</th>
                    <th class="num-col">Total Rows</th>
                    <th class="num-col">Success</th>
                    <th class="num-col">Failed</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 90px;">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $b)
                <tr>
                    <td class="font-monospace fw-bold">#{{ $b->id }}</td>
                    <td>
                        <span class="badge bg-light text-dark border text-capitalize">
                            {{ str_replace('_', ' ', $b->type) }}
                        </span>
                    </td>
                    <td>{{ $b->file_name }}</td>
                    <td>{{ $b->user->name }}</td>
                    <td>{{ $b->created_at->format('M d, Y H:i') }}</td>
                    <td class="num-col">{{ $b->total_rows }}</td>
                    <td class="num-col text-success fw-bold">{{ $b->successful_rows }}</td>
                    <td class="num-col {{ $b->failed_rows > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $b->failed_rows }}</td>
                    <td>
                        @if($b->status === 'completed')
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">COMPLETED</span>
                        @elseif($b->status === 'partially_failed')
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">PARTIAL</span>
                        @else
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">FAILED</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm view-batch-btn" data-batch-id="{{ $b->id }}" data-bs-toggle="modal" data-bs-target="#batchDetailModal">
                            <i class="fa-solid fa-list-ul"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">No bulk import batches recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($batches->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $batches->links() }}
    </div>
    @endif
</div>

<!-- Batch Error / Details Modal -->
<div class="modal fade" id="batchDetailModal" tabindex="-1" aria-labelledby="batchDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="batchDetailModalLabel"><i class="fa-solid fa-file-lines text-primary me-2"></i>Import Batch Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="batchLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small text-muted mt-2">Loading batch metrics...</div>
                </div>
                <div id="batchContent" style="display: none;">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <span class="text-muted small">File:</span>
                            <div class="fw-bold" id="mFileName">-</div>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small">Status:</span>
                            <div id="mStatus">-</div>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted small">Processed Rows:</span>
                            <div class="fw-bold" id="mRows">-</div>
                        </div>
                    </div>
                    <h6 class="fw-bold mb-2">Error Log & Row Messages:</h6>
                    <div id="mErrors" class="p-3 bg-light rounded font-monospace small" style="max-height: 250px; overflow-y: auto;">
                        No errors recorded for this batch.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.view-batch-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const batchId = this.dataset.batchId;
            const loading = document.getElementById('batchLoading');
            const content = document.getElementById('batchContent');

            loading.style.display = 'block';
            content.style.display = 'none';

            fetch(`/imports/batches/${batchId}`)
                .then(res => res.json())
                .then(data => {
                    loading.style.display = 'none';
                    content.style.display = 'block';

                    document.getElementById('mFileName').innerText = data.file_name;
                    document.getElementById('mStatus').innerHTML = `<span class="badge bg-secondary text-uppercase">${data.status}</span>`;
                    document.getElementById('mRows').innerText = `${data.successful_rows} succeeded / ${data.total_rows} total (${data.failed_rows} errors)`;

                    const errorsDiv = document.getElementById('mErrors');
                    if (data.error_summary && data.error_summary.length > 0) {
                        let html = '<ul class="list-unstyled text-danger mb-0">';
                        data.error_summary.forEach(err => {
                            html += `<li class="mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> ${err}</li>`;
                        });
                        html += '</ul>';
                        errorsDiv.innerHTML = html;
                    } else {
                        errorsDiv.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> Clean import with zero errors! All records committed to database.</span>';
                    }
                })
                .catch(err => {
                    loading.innerHTML = '<div class="text-danger">Failed to load batch data.</div>';
                });
        });
    });
</script>
@endsection
