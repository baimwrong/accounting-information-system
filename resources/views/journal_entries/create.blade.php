@extends('layouts.app')

@section('title', 'New Journal Entry')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-11">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-book-journal-whills text-primary fs-5"></i>
                    <h5 class="card-title mb-0 fw-bold">Record General Journal Entry</h5>
                </div>
                <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Journal Entries
                </a>
            </div>

            <form action="{{ route('journal-entries.store') }}" method="POST" id="journalForm">
                @csrf
                <div class="card-body p-4">
                    <!-- Top header fields -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Entry Number</label>
                            <input type="text" class="form-control bg-light font-monospace" value="{{ $suggestedNumber }}" disabled readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">External Reference</label>
                            <input type="text" name="reference" class="form-control" placeholder="e.g. Check #, Wire Ref" value="{{ old('reference') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Posting Option</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="auto_post" id="autoPost" value="1" checked>
                                <label class="form-check-label fw-semibold small" for="autoPost">Post directly to GL</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Description / Memo <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="2" placeholder="State transaction rationale or purpose..." required>{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">Double-Entry Lines</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addLineBtn">
                            <i class="fa-solid fa-plus me-1"></i> Add Line
                        </button>
                    </div>

                    <!-- Line items table -->
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle" id="linesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 280px;">Account <span class="text-danger">*</span></th>
                                    <th>Description / Memo</th>
                                    <th style="width: 160px;" class="text-end">Debit ($)</th>
                                    <th style="width: 160px;" class="text-end">Credit ($)</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="linesContainer">
                                <!-- Row 1 -->
                                <tr class="line-row">
                                    <td>
                                        <select name="items[0][account_id]" class="form-select form-select-sm account-select" required>
                                            <option value="">-- Choose Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="items[0][memo]" class="form-control form-control-sm" placeholder="Line memo">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="items[0][debit_amount]" class="form-control form-control-sm text-end debit-input" placeholder="0.00">
                                        <input type="hidden" name="items[0][type]" class="item-type" value="debit">
                                        <input type="hidden" name="items[0][amount]" class="item-amount" value="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="items[0][credit_amount]" class="form-control form-control-sm text-end credit-input" placeholder="0.00">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" disabled><i class="fa-solid fa-trash-can"></i></button>
                                    </td>
                                </tr>
                                <!-- Row 2 -->
                                <tr class="line-row">
                                    <td>
                                        <select name="items[1][account_id]" class="form-select form-select-sm account-select" required>
                                            <option value="">-- Choose Account --</option>
                                            @foreach($accounts as $acc)
                                                <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }} ({{ ucfirst($acc->type) }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="items[1][memo]" class="form-control form-control-sm" placeholder="Line memo">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="items[1][debit_amount]" class="form-control form-control-sm text-end debit-input" placeholder="0.00">
                                        <input type="hidden" name="items[1][type]" class="item-type" value="credit">
                                        <input type="hidden" name="items[1][amount]" class="item-amount" value="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="items[1][credit_amount]" class="form-control form-control-sm text-end credit-input" placeholder="0.00">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" disabled><i class="fa-solid fa-trash-can"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="table-light fw-bold">
                                    <td colspan="2" class="text-end">Totals:</td>
                                    <td class="text-end font-monospace" id="totalDebitText">$0.00</td>
                                    <td class="text-end font-monospace" id="totalCreditText">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Balance Status Indicator -->
                    <div class="d-flex justify-content-between align-items-center p-3 rounded mb-4" id="balanceStatusBox" style="background-color: #f1f5f9;">
                        <div>
                            <span class="fw-bold me-2">Equation Status:</span>
                            <span id="balanceBadge" class="badge bg-secondary">Awaiting input</span>
                        </div>
                        <div class="text-muted small">
                            Difference: <span id="diffAmountText" class="fw-bold font-monospace">$0.00</span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('journal-entries.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold" id="submitBtn" disabled>
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Journal Entry
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
    let rowIndex = 2;
    const accountsData = @json($accounts->map(fn($a) => ['id' => $a->id, 'text' => "{$a->code} - {$a->name} (" . ucfirst($a->type) . ")"]));

    function calculateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        const rows = document.querySelectorAll('.line-row');

        rows.forEach(row => {
            const debitInp = row.querySelector('.debit-input');
            const creditInp = row.querySelector('.credit-input');
            const typeInp = row.querySelector('.item-type');
            const amountInp = row.querySelector('.item-amount');

            const debitVal = parseFloat(debitInp.value) || 0;
            const creditVal = parseFloat(creditInp.value) || 0;

            if (debitVal > 0) {
                totalDebit += debitVal;
                typeInp.value = 'debit';
                amountInp.value = debitVal;
            } else if (creditVal > 0) {
                totalCredit += creditVal;
                typeInp.value = 'credit';
                amountInp.value = creditVal;
            } else {
                amountInp.value = 0;
            }
        });

        document.getElementById('totalDebitText').innerText = '$' + totalDebit.toFixed(2);
        document.getElementById('totalCreditText').innerText = '$' + totalCredit.toFixed(2);

        const diff = Math.abs(totalDebit - totalCredit);
        document.getElementById('diffAmountText').innerText = '$' + diff.toFixed(2);

        const badge = document.getElementById('balanceBadge');
        const submitBtn = document.getElementById('submitBtn');

        if (totalDebit > 0 && totalCredit > 0 && diff < 0.001) {
            badge.className = 'badge bg-success';
            badge.innerText = 'Balanced (Debits = Credits)';
            submitBtn.disabled = false;
        } else if (totalDebit === 0 && totalCredit === 0) {
            badge.className = 'badge bg-secondary';
            badge.innerText = 'Awaiting input';
            submitBtn.disabled = true;
        } else {
            badge.className = 'badge bg-danger';
            badge.innerText = 'Out of Balance';
            submitBtn.disabled = true;
        }

        // Enable/disable remove button if rows > 2
        const removeBtns = document.querySelectorAll('.remove-row-btn');
        removeBtns.forEach(btn => {
            btn.disabled = rows.length <= 2;
        });
    }

    // Handle mutual exclusivity of debit/credit on same row
    document.getElementById('linesContainer').addEventListener('input', function (e) {
        if (e.target.classList.contains('debit-input') && e.target.value > 0) {
            const row = e.target.closest('tr');
            row.querySelector('.credit-input').value = '';
        } else if (e.target.classList.contains('credit-input') && e.target.value > 0) {
            const row = e.target.closest('tr');
            row.querySelector('.debit-input').value = '';
        }
        calculateTotals();
    });

    // Add Line
    document.getElementById('addLineBtn').addEventListener('click', function () {
        const tbody = document.getElementById('linesContainer');
        const tr = document.createElement('tr');
        tr.className = 'line-row';

        let options = '<option value="">-- Choose Account --</option>';
        accountsData.forEach(acc => {
            options += `<option value="${acc.id}">${acc.text}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][account_id]" class="form-select form-select-sm account-select" required>
                    ${options}
                </select>
            </td>
            <td>
                <input type="text" name="items[${rowIndex}][memo]" class="form-control form-control-sm" placeholder="Line memo">
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${rowIndex}][debit_amount]" class="form-control form-control-sm text-end debit-input" placeholder="0.00">
                <input type="hidden" name="items[${rowIndex}][type]" class="item-type" value="debit">
                <input type="hidden" name="items[${rowIndex}][amount]" class="item-amount" value="0">
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="items[${rowIndex}][credit_amount]" class="form-control form-control-sm text-end credit-input" placeholder="0.00">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn"><i class="fa-solid fa-trash-can"></i></button>
            </td>
        `;

        tbody.appendChild(tr);
        rowIndex++;
        calculateTotals();
    });

    // Remove row
    document.getElementById('linesContainer').addEventListener('click', function (e) {
        if (e.target.closest('.remove-row-btn')) {
            const row = e.target.closest('tr');
            if (document.querySelectorAll('.line-row').length > 2) {
                row.remove();
                calculateTotals();
            }
        }
    });

    calculateTotals();
</script>
@endsection
