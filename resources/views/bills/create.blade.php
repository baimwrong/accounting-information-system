@extends('layouts.app')

@section('title', 'New Purchase Bill')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-11">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-receipt text-danger fs-5"></i>
                    <h5 class="card-title mb-0 fw-bold">Record Purchase Bill (Vendor Invoice)</h5>
                </div>
                <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Bills
                </a>
            </div>

            <form action="{{ route('bills.store') }}" method="POST" id="billForm">
                @csrf
                <div class="card-body p-4">
                    <!-- Top header fields -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Vendor / Supplier <span class="text-danger">*</span></label>
                            <select name="vendor_id" class="form-select" required>
                                <option value="">-- Choose Vendor --</option>
                                @foreach($vendors as $v)
                                    <option value="{{ $v->id }}" {{ old('vendor_id') == $v->id ? 'selected' : '' }}>
                                        {{ $v->name }} {{ $v->company_name ? "({$v->company_name})" : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Bill / Invoice Reference <span class="text-danger">*</span></label>
                            <input type="text" name="bill_number" class="form-control font-monospace" value="{{ old('bill_number', $billNumber) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">Bill Date <span class="text-danger">*</span></label>
                            <input type="date" name="bill_date" class="form-control" value="{{ old('bill_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" value="{{ old('due_date', date('Y-m-d', strtotime('+30 days'))) }}" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">Expense & Purchase Items</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addItemBtn">
                            <i class="fa-solid fa-plus me-1"></i> Add Line
                        </button>
                    </div>

                    <!-- Line items table -->
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 200px;">Catalog Item (Optional)</th>
                                    <th style="min-width: 180px;">Expense/Asset Account <span class="text-danger">*</span></th>
                                    <th>Description <span class="text-danger">*</span></th>
                                    <th style="width: 100px;" class="text-end">Qty</th>
                                    <th style="width: 130px;" class="text-end">Cost Price</th>
                                    <th style="width: 100px;" class="text-end">Tax %</th>
                                    <th style="width: 130px;" class="text-end">Total</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsContainer">
                                <tr class="item-row">
                                    <td>
                                        <select name="items[0][product_id]" class="form-select form-select-sm product-select">
                                            <option value="">-- Direct Expense --</option>
                                            @foreach($products as $prod)
                                                <option value="{{ $prod->id }}" data-cost="{{ $prod->purchase_price }}" data-tax="{{ $prod->tax_rate }}" data-account="{{ $prod->expense_account_id }}" data-desc="{{ $prod->description ?: $prod->name }}">
                                                    {{ $prod->code }} - {{ $prod->name }} (${{ number_format($prod->purchase_price, 2) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="items[0][account_id]" class="form-select form-select-sm account-select" required>
                                            @foreach($expenseAccounts as $exp)
                                                <option value="{{ $exp->id }}">{{ $exp->code }} - {{ $exp->name }} ({{ ucfirst($exp->type) }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="items[0][description]" class="form-control form-control-sm desc-input" placeholder="Expense description" required>
                                    </td>
                                    <td>
                                        <input type="number" step="1" min="1" name="items[0][quantity]" class="form-control form-control-sm text-end qty-input" value="1" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="items[0][unit_price]" class="form-control form-control-sm text-end price-input" value="0.00" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="100" name="items[0][tax_percent]" class="form-control form-control-sm text-end tax-input" value="0.00">
                                    </td>
                                    <td class="text-end font-monospace fw-bold line-total-text">$0.00</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" disabled><i class="fa-solid fa-trash-can"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Summary -->
                    <div class="row justify-content-end mb-4">
                        <div class="col-md-5">
                            <div class="card bg-light p-3 border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold font-monospace" id="subtotalText">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Estimated Tax / VAT:</span>
                                    <span class="fw-semibold font-monospace" id="taxTotalText">$0.00</span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between fs-5 fw-bold">
                                    <span>Total Payable:</span>
                                    <span class="text-danger font-monospace" id="grandTotalText">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Internal Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Purchase order references, delivery verification notes...">{{ old('notes') }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('bills.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                            <i class="fa-solid fa-receipt me-1"></i> Approve & Post Bill
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let rowIndex = 1;
    const productsData = @json($products->map(fn($p) => [
        'id' => $p->id,
        'text' => "{$p->code} - {$p->name} (\$" . number_format($p->purchase_price, 2) . ")",
        'cost' => $p->purchase_price,
        'tax' => $p->tax_rate,
        'account' => $p->expense_account_id,
        'desc' => $p->description ?: $p->name
    ]));
    const expenseAccountsData = @json($expenseAccounts->map(fn($a) => ['id' => $a->id, 'text' => "{$a->code} - {$a->name} (" . ucfirst($a->type) . ")"]));

    function calculateBillTotals() {
        let subtotal = 0;
        let taxTotal = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const taxPct = parseFloat(row.querySelector('.tax-input').value) || 0;

            const base = qty * price;
            const tax = base * (taxPct / 100);
            const total = base + tax;

            subtotal += base;
            taxTotal += tax;

            row.querySelector('.line-total-text').innerText = '$' + total.toFixed(2);
        });

        const grand = subtotal + taxTotal;

        document.getElementById('subtotalText').innerText = '$' + subtotal.toFixed(2);
        document.getElementById('taxTotalText').innerText = '$' + taxTotal.toFixed(2);
        document.getElementById('grandTotalText').innerText = '$' + grand.toFixed(2);

        const rows = document.querySelectorAll('.item-row');
        document.querySelectorAll('.remove-row-btn').forEach(btn => {
            btn.disabled = rows.length <= 1;
        });
    }

    document.getElementById('itemsContainer').addEventListener('input', calculateBillTotals);

    document.getElementById('itemsContainer').addEventListener('change', function (e) {
        if (e.target.classList.contains('product-select')) {
            const selectedOpt = e.target.options[e.target.selectedIndex];
            if (selectedOpt.value) {
                const row = e.target.closest('tr');
                row.querySelector('.price-input').value = selectedOpt.dataset.cost || '0.00';
                row.querySelector('.tax-input').value = selectedOpt.dataset.tax || '0.00';
                row.querySelector('.desc-input').value = selectedOpt.dataset.desc || '';
                if (selectedOpt.dataset.account) {
                    row.querySelector('.account-select').value = selectedOpt.dataset.account;
                }
            }
            calculateBillTotals();
        }
    });

    document.getElementById('addItemBtn').addEventListener('click', function () {
        const tbody = document.getElementById('itemsContainer');
        const tr = document.createElement('tr');
        tr.className = 'item-row';

        let prodOptions = '<option value="">-- Direct Expense --</option>';
        productsData.forEach(p => {
            prodOptions += `<option value="${p.id}" data-cost="${p.cost}" data-tax="${p.tax}" data-account="${p.account}" data-desc="${p.desc}">${p.text}</option>`;
        });

        let accOptions = '';
        expenseAccountsData.forEach(a => {
            accOptions += `<option value="${a.id}">${a.text}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][product_id]" class="form-select form-select-sm product-select">
                    ${prodOptions}
                </select>
            </td>
            <td>
                <select name="items[${rowIndex}][account_id]" class="form-select form-select-sm account-select" required>
                    ${accOptions}
                </select>
            </td>
            <td>
                <input type="text" name="items[${rowIndex}][description]" class="form-control form-control-sm desc-input" placeholder="Expense description" required>
            </td>
            <td>
                <input type="number" step="1" min="1" name="items[${rowIndex}][quantity]" class="form-control form-control-sm text-end qty-input" value="1" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${rowIndex}][unit_price]" class="form-control form-control-sm text-end price-input" value="0.00" required>
            </td>
            <td>
                <input type="number" step="0.01" min="0" max="100" name="items[${rowIndex}][tax_percent]" class="form-control form-control-sm text-end tax-input" value="0.00">
            </td>
            <td class="text-end font-monospace fw-bold line-total-text">$0.00</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn"><i class="fa-solid fa-trash-can"></i></button>
            </td>
        `;

        tbody.appendChild(tr);
        rowIndex++;
        calculateBillTotals();
    });

    document.getElementById('itemsContainer').addEventListener('click', function (e) {
        if (e.target.closest('.remove-row-btn')) {
            const row = e.target.closest('tr');
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calculateBillTotals();
            }
        }
    });

    calculateBillTotals();
</script>
@endsection
