<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkImportService
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    /**
     * Parse CSV file into an array of associative rows.
     */
    protected function parseCsv(UploadedFile|string $file): array
    {
        $path = is_string($file) ? $file : $file->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new Exception('Could not open the uploaded CSV file.');
        }

        $rows = [];
        $header = null;
        $rowNum = 0;

        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            $rowNum++;
            // Trim BOM if present on first column
            if ($rowNum === 1 && isset($data[0])) {
                $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]);
            }

            // Clean whitespace
            $data = array_map('trim', $data);

            if (! $header) {
                $header = array_map('strtolower', $data);

                continue;
            }

            if (empty(array_filter($data, fn ($v) => $v !== ''))) {
                continue; // Skip blank rows
            }

            if (count($data) === count($header)) {
                $rows[] = [
                    '_row' => $rowNum,
                    'data' => array_combine($header, $data),
                ];
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Import Chart of Accounts.
     */
    public function importAccounts(UploadedFile $file, User $user): ImportBatch
    {
        $batch = ImportBatch::create([
            'user_id' => $user->id,
            'type' => 'chart_of_accounts',
            'file_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        $rows = $this->parseCsv($file);
        $total = count($rows);
        $success = 0;
        $errors = [];

        foreach ($rows as $item) {
            $rowNum = $item['_row'];
            $data = $item['data'];

            try {
                if (empty($data['code']) || empty($data['name']) || empty($data['type'])) {
                    throw new Exception('Fields "code", "name", and "type" are required.');
                }

                $type = strtolower($data['type']);
                if (! in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
                    throw new Exception("Invalid account type '{$data['type']}'. Allowed: asset, liability, equity, revenue, expense.");
                }

                $normalBalance = ! empty($data['normal_balance'])
                    ? strtolower($data['normal_balance'])
                    : (in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit');

                $parentId = null;
                if (! empty($data['parent_code'])) {
                    $parent = Account::where('code', $data['parent_code'])->first();
                    $parentId = $parent?->id;
                }

                Account::updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $data['name'],
                        'type' => $type,
                        'sub_type' => $data['sub_type'] ?? null,
                        'normal_balance' => $normalBalance,
                        'parent_id' => $parentId,
                        'description' => $data['description'] ?? null,
                        'is_active' => true,
                    ]
                );

                $success++;
            } catch (Exception $e) {
                $errors[] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        $status = empty($errors) ? 'completed' : ($success > 0 ? 'partially_failed' : 'failed');
        $batch->update([
            'total_rows' => $total,
            'successful_rows' => $success,
            'failed_rows' => count($errors),
            'status' => $status,
            'error_summary' => $errors,
        ]);

        AuditLog::record(
            $user,
            'bulk_import',
            "Bulk imported Chart of Accounts: {$success}/{$total} successful",
            $batch
        );

        return $batch;
    }

    /**
     * Import Contacts (Customers and Vendors).
     */
    public function importContacts(UploadedFile $file, User $user): ImportBatch
    {
        $batch = ImportBatch::create([
            'user_id' => $user->id,
            'type' => 'contacts',
            'file_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        $rows = $this->parseCsv($file);
        $total = count($rows);
        $success = 0;
        $errors = [];

        foreach ($rows as $item) {
            $rowNum = $item['_row'];
            $data = $item['data'];

            try {
                if (empty($data['name']) || empty($data['type'])) {
                    throw new Exception('Fields "name" and "type" are required.');
                }

                $type = strtolower($data['type']);
                if (! in_array($type, ['customer', 'vendor', 'both'], true)) {
                    throw new Exception("Type must be 'customer', 'vendor', or 'both'. Got '{$data['type']}'.");
                }

                Contact::create([
                    'type' => $type,
                    'name' => $data['name'],
                    'company_name' => $data['company_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'tax_number' => $data['tax_number'] ?? null,
                    'address' => $data['address'] ?? null,
                    'credit_limit' => ! empty($data['credit_limit']) ? (float) $data['credit_limit'] : null,
                    'opening_balance' => ! empty($data['opening_balance']) ? (float) $data['opening_balance'] : 0,
                    'is_active' => true,
                ]);

                $success++;
            } catch (Exception $e) {
                $errors[] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        $status = empty($errors) ? 'completed' : ($success > 0 ? 'partially_failed' : 'failed');
        $batch->update([
            'total_rows' => $total,
            'successful_rows' => $success,
            'failed_rows' => count($errors),
            'status' => $status,
            'error_summary' => $errors,
        ]);

        AuditLog::record(
            $user,
            'bulk_import',
            "Bulk imported Contacts: {$success}/{$total} successful",
            $batch
        );

        return $batch;
    }

    /**
     * Import Products / Inventory / Services.
     */
    public function importProducts(UploadedFile $file, User $user): ImportBatch
    {
        $batch = ImportBatch::create([
            'user_id' => $user->id,
            'type' => 'products',
            'file_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        $rows = $this->parseCsv($file);
        $total = count($rows);
        $success = 0;
        $errors = [];

        foreach ($rows as $item) {
            $rowNum = $item['_row'];
            $data = $item['data'];

            try {
                if (empty($data['code']) || empty($data['name']) || empty($data['income_account_code']) || empty($data['expense_account_code'])) {
                    throw new Exception('Fields "code", "name", "income_account_code", and "expense_account_code" are required.');
                }

                $incomeAccount = Account::where('code', $data['income_account_code'])->first();
                if (! $incomeAccount) {
                    throw new Exception("Income account code '{$data['income_account_code']}' does not exist.");
                }

                $expenseAccount = Account::where('code', $data['expense_account_code'])->first();
                if (! $expenseAccount) {
                    throw new Exception("Expense account code '{$data['expense_account_code']}' does not exist.");
                }

                $assetAccount = null;
                if (! empty($data['asset_account_code'])) {
                    $assetAccount = Account::where('code', $data['asset_account_code'])->first();
                }

                Product::updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $data['name'],
                        'type' => ! empty($data['type']) && in_array(strtolower($data['type']), ['goods', 'service']) ? strtolower($data['type']) : 'goods',
                        'income_account_id' => $incomeAccount->id,
                        'expense_account_id' => $expenseAccount->id,
                        'asset_account_id' => $assetAccount?->id,
                        'sale_price' => ! empty($data['sale_price']) ? (float) $data['sale_price'] : 0,
                        'purchase_price' => ! empty($data['purchase_price']) ? (float) $data['purchase_price'] : 0,
                        'tax_rate' => ! empty($data['tax_rate']) ? (float) $data['tax_rate'] : 0,
                        'quantity_on_hand' => ! empty($data['quantity_on_hand']) ? (float) $data['quantity_on_hand'] : 0,
                        'description' => $data['description'] ?? null,
                        'is_active' => true,
                    ]
                );

                $success++;
            } catch (Exception $e) {
                $errors[] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        $status = empty($errors) ? 'completed' : ($success > 0 ? 'partially_failed' : 'failed');
        $batch->update([
            'total_rows' => $total,
            'successful_rows' => $success,
            'failed_rows' => count($errors),
            'status' => $status,
            'error_summary' => $errors,
        ]);

        AuditLog::record(
            $user,
            'bulk_import',
            "Bulk imported Products: {$success}/{$total} successful",
            $batch
        );

        return $batch;
    }

    /**
     * Import Journal Entries (Multi-row CSV grouped by entry_number).
     */
    public function importJournalEntries(UploadedFile $file, User $user): ImportBatch
    {
        $batch = ImportBatch::create([
            'user_id' => $user->id,
            'type' => 'journal_entries',
            'file_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        $rows = $this->parseCsv($file);
        $totalEntries = 0;
        $success = 0;
        $errors = [];

        // Group rows by entry_number
        $grouped = [];
        foreach ($rows as $item) {
            $data = $item['data'];
            $entryNum = $data['entry_number'] ?? 'DEFAULT';
            $grouped[$entryNum][] = $item;
        }

        $totalEntries = count($grouped);

        foreach ($grouped as $entryNum => $groupRows) {
            try {
                $firstRow = $groupRows[0]['data'];
                $entryDate = ! empty($firstRow['date']) ? $firstRow['date'] : Carbon::now()->toDateString();
                $ref = $firstRow['reference'] ?? null;
                $desc = $firstRow['description'] ?? "Imported Entry {$entryNum}";

                $items = [];
                $totalDebit = 0.0;
                $totalCredit = 0.0;

                foreach ($groupRows as $gItem) {
                    $rNum = $gItem['_row'];
                    $rData = $gItem['data'];

                    if (empty($rData['account_code'])) {
                        throw new Exception("Row {$rNum}: account_code is missing.");
                    }

                    $account = Account::where('code', $rData['account_code'])->first();
                    if (! $account) {
                        throw new Exception("Row {$rNum}: Account code '{$rData['account_code']}' does not exist.");
                    }

                    $debit = ! empty($rData['debit']) ? round((float) $rData['debit'], 4) : 0.0;
                    $credit = ! empty($rData['credit']) ? round((float) $rData['credit'], 4) : 0.0;

                    if ($debit > 0 && $credit > 0) {
                        throw new Exception("Row {$rNum}: A line cannot have both debit and credit amounts.");
                    }

                    if ($debit <= 0 && $credit <= 0) {
                        throw new Exception("Row {$rNum}: Line must have either debit or credit amount.");
                    }

                    $type = $debit > 0 ? 'debit' : 'credit';
                    $amount = $debit > 0 ? $debit : $credit;

                    if ($type === 'debit') {
                        $totalDebit += $amount;
                    } else {
                        $totalCredit += $amount;
                    }

                    $items[] = [
                        'account_id' => $account->id,
                        'type' => $type,
                        'amount' => $amount,
                        'memo' => $rData['memo'] ?? null,
                    ];
                }

                if (abs($totalDebit - $totalCredit) > 0.0001) {
                    throw new Exception("Entry {$entryNum} is out of balance! Debit: {$totalDebit}, Credit: {$totalCredit}");
                }

                $this->accountingService->createJournalEntry([
                    'entry_number' => $entryNum !== 'DEFAULT' ? $entryNum : null,
                    'date' => $entryDate,
                    'reference' => $ref,
                    'description' => $desc,
                    'source_type' => 'bulk_import',
                    'items' => $items,
                ], $user, true);

                $success++;
            } catch (Exception $e) {
                $errors[] = "Entry {$entryNum}: ".$e->getMessage();
            }
        }

        $status = empty($errors) ? 'completed' : ($success > 0 ? 'partially_failed' : 'failed');
        $batch->update([
            'total_rows' => $totalEntries,
            'successful_rows' => $success,
            'failed_rows' => count($errors),
            'status' => $status,
            'error_summary' => $errors,
        ]);

        AuditLog::record(
            $user,
            'bulk_import',
            "Bulk imported Journal Entries: {$success}/{$totalEntries} successful",
            $batch
        );

        return $batch;
    }

    /**
     * Generate downloadable sample CSV template with sample data.
     */
    public function downloadSampleTemplate(string $type): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"template_{$type}.csv\"",
        ];

        return response()->stream(function () use ($type) {
            $handle = fopen('php://output', 'w');

            switch ($type) {
                case 'chart_of_accounts':
                    fputcsv($handle, ['code', 'name', 'type', 'sub_type', 'normal_balance', 'parent_code', 'description']);
                    fputcsv($handle, ['1010', 'Petty Cash', 'asset', 'current_asset', 'debit', '', 'Daily operational cash']);
                    fputcsv($handle, ['1020', 'Operating Bank Account', 'asset', 'current_asset', 'debit', '', 'Main checking account']);
                    fputcsv($handle, ['1200', 'Accounts Receivable', 'asset', 'current_asset', 'debit', '', 'Amounts owed by customers']);
                    fputcsv($handle, ['2010', 'Accounts Payable', 'liability', 'current_liability', 'credit', '', 'Amounts owed to suppliers']);
                    fputcsv($handle, ['3010', 'Common Capital Stock', 'equity', 'equity', 'credit', '', 'Initial paid-up capital']);
                    fputcsv($handle, ['4010', 'Consulting & Software Revenue', 'revenue', 'operating_revenue', 'credit', '', 'Revenue from core services']);
                    fputcsv($handle, ['5010', 'Cost of Goods Sold', 'expense', 'cost_of_goods_sold', 'debit', '', 'Direct costs']);
                    fputcsv($handle, ['6010', 'Office Rent Expense', 'expense', 'operating_expense', 'debit', '', 'Monthly facilities lease']);
                    break;

                case 'contacts':
                    fputcsv($handle, ['type', 'name', 'company_name', 'email', 'phone', 'tax_number', 'address', 'credit_limit', 'opening_balance']);
                    fputcsv($handle, ['customer', 'Acme Corporation', 'Acme Global Ltd', 'billing@acme.com', '+1-555-0100', 'TAX-998811', '100 Silicon Way, Tech City', '25000.00', '0.00']);
                    fputcsv($handle, ['customer', 'Nexus Enterprises', 'Nexus Group', 'ap@nexus.io', '+1-555-0144', 'TAX-334455', '45 Innovation Blvd', '15000.00', '0.00']);
                    fputcsv($handle, ['vendor', 'Cloud Infra Providers', 'Cloud Provider Inc', 'support@cloudinfra.com', '+1-555-0199', 'TAX-772211', '700 Data Center Dr', '50000.00', '0.00']);
                    fputcsv($handle, ['vendor', 'Prime Office Supplies', 'Prime Retail LLC', 'sales@primeoffice.com', '+1-555-0182', 'TAX-661122', '12 Market St', '10000.00', '0.00']);
                    break;

                case 'products':
                    fputcsv($handle, ['code', 'name', 'type', 'sale_price', 'purchase_price', 'tax_rate', 'income_account_code', 'expense_account_code', 'asset_account_code', 'quantity_on_hand', 'description']);
                    fputcsv($handle, ['SRV-CONSULT', 'Enterprise Consulting Hour', 'service', '150.00', '0.00', '10.00', '4010', '6010', '', '0', 'Senior financial advisory consulting']);
                    fputcsv($handle, ['PRD-SERVER', 'Rackmount Server Hardware', 'goods', '3200.00', '2100.00', '10.00', '4010', '5010', '1020', '15', 'Production grade 2U database server']);
                    break;

                case 'journal_entries':
                    fputcsv($handle, ['entry_number', 'date', 'reference', 'description', 'account_code', 'debit', 'credit', 'memo']);
                    fputcsv($handle, ['JE-2026-0001', date('Y-m-d'), 'REF-STARTUP', 'Owner Capital Injection', '1020', '50000.00', '0.00', 'Bank deposit']);
                    fputcsv($handle, ['JE-2026-0001', date('Y-m-d'), 'REF-STARTUP', 'Owner Capital Injection', '3010', '0.00', '50000.00', 'Capital equity']);
                    fputcsv($handle, ['JE-2026-0002', date('Y-m-d'), 'CHK-1002', 'Office Rent Advance Payment', '6010', '3500.00', '0.00', 'October Rent']);
                    fputcsv($handle, ['JE-2026-0002', date('Y-m-d'), 'CHK-1002', 'Office Rent Advance Payment', '1020', '0.00', '3500.00', 'Bank wire']);
                    break;

                default:
                    fputcsv($handle, ['error', 'Unknown template type']);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
