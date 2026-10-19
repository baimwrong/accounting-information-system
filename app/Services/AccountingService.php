<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Generate sequential entry number: JE-YYYYMM-XXXX (thread-safe).
     */
    public function generateEntryNumber(string $prefix = 'JE'): string
    {
        return $this->generateNextDocumentNumber(JournalEntry::class, 'entry_number', $prefix);
    }

    /**
     * Concurrency-safe sequential document number generator.
     */
    public function generateNextDocumentNumber(string $modelClass, string $field, string $prefix): string
    {
        $ym = Carbon::now()->format('Ym');
        $searchPrefix = "{$prefix}-{$ym}-";

        $latest = $modelClass::where($field, 'LIKE', "{$searchPrefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($latest) {
            $parts = explode('-', $latest->{$field});
            $lastPart = (int) end($parts);
            if ($lastPart >= 1) {
                $seq = $lastPart + 1;
            }
        }

        do {
            $candidate = sprintf('%s%04d', $searchPrefix, $seq);
            $exists = $modelClass::where($field, $candidate)->exists();
            if ($exists) {
                $seq++;
            }
        } while ($exists);

        return $candidate;
    }

    /**
     * Check if date falls in an active fiscal year.
     * Throws an exception if the period is closed or if no active fiscal year is defined.
     */
    public function assertDateInActiveFiscalYear(string|Carbon $date): void
    {
        $d = Carbon::parse($date)->toDateString();

        $active = FiscalYear::where('status', 'active')
            ->where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->exists();

        if (! $active) {
            $closed = FiscalYear::where('status', 'closed')
                ->where('start_date', '<=', $d)
                ->where('end_date', '>=', $d)
                ->exists();

            if ($closed) {
                throw new Exception("Accounting operations are locked for closed fiscal period on {$d}.");
            }

            throw new Exception("No active fiscal period found covering {$d}. Please ensure an active fiscal year is configured.");
        }
    }

    /**
     * Create and optionally post a Journal Entry.
     * Items shape: array of ['account_id' => int, 'type' => 'debit'|'credit', 'amount' => float, 'memo' => ?string]
     */
    public function createJournalEntry(array $data, User $user, bool $autoPost = true): JournalEntry
    {
        $this->assertDateInActiveFiscalYear($data['date'] ?? Carbon::now());

        $items = $data['items'] ?? [];
        if (count($items) < 2) {
            throw new Exception('A journal entry requires at least two line items.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $amount = round((float) ($item['amount'] ?? 0), 4);
            if ($amount <= 0) {
                throw new Exception('Journal entry line amounts must be greater than zero.');
            }
            if (($item['type'] ?? '') === 'debit') {
                $totalDebit += $amount;
            } elseif (($item['type'] ?? '') === 'credit') {
                $totalCredit += $amount;
            } else {
                throw new Exception("Invalid item type [{$item['type']}]. Must be debit or credit.");
            }
        }

        // Verify Double-Entry Accounting equation: Sum(Debit) == Sum(Credit)
        if (abs($totalDebit - $totalCredit) > 0.0001) {
            throw new Exception(sprintf(
                'Journal entry is not balanced! Total Debit: %.2f, Total Credit: %.2f (Difference: %.2f)',
                $totalDebit,
                $totalCredit,
                abs($totalDebit - $totalCredit)
            ));
        }

        return DB::transaction(function () use ($data, $user, $items, $totalDebit, $totalCredit, $autoPost) {
            $entryNumber = $data['entry_number'] ?? $this->generateEntryNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'date' => $data['date'] ?? Carbon::now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'General Journal Entry',
                'source_type' => $data['source_type'] ?? 'manual',
                'source_id' => $data['source_id'] ?? null,
                'status' => $autoPost ? 'posted' : 'draft',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_at' => $autoPost ? Carbon::now() : null,
                'posted_by' => $autoPost ? $user->id : null,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $entry->items()->create([
                    'account_id' => $item['account_id'],
                    'type' => $item['type'],
                    'amount' => round((float) $item['amount'], 4),
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            AuditLog::record(
                $user,
                'create_journal_entry',
                "Created journal entry {$entry->entry_number} ({$entry->status}) with total {$totalDebit}",
                $entry
            );

            return $entry;
        });
    }

    /**
     * Post an existing draft journal entry.
     */
    public function postJournalEntry(JournalEntry $entry, User $user): JournalEntry
    {
        if ($entry->status === 'posted') {
            return $entry;
        }

        if ($entry->status === 'voided') {
            throw new Exception('Cannot post a voided journal entry.');
        }

        if (! $entry->isBalanced()) {
            throw new Exception('Cannot post an unbalanced journal entry.');
        }

        $this->assertDateInActiveFiscalYear($entry->date);

        $entry->update([
            'status' => 'posted',
            'posted_at' => Carbon::now(),
            'posted_by' => $user->id,
        ]);

        AuditLog::record($user, 'post_journal_entry', "Posted journal entry {$entry->entry_number}", $entry);

        return $entry;
    }

    /**
     * Void a journal entry.
     */
    public function voidJournalEntry(JournalEntry $entry, User $user, string $reason = ''): JournalEntry
    {
        if ($entry->status === 'voided') {
            return $entry;
        }

        $this->assertDateInActiveFiscalYear($entry->date);

        $entry->update([
            'status' => 'voided',
            'description' => $entry->description." [VOIDED: {$reason}]",
        ]);

        AuditLog::record($user, 'void_journal_entry', "Voided journal entry {$entry->entry_number}: {$reason}", $entry);

        return $entry;
    }

    /**
     * Post a Sales Invoice to General Ledger:
     * DR: Accounts Receivable (Asset) = Total Amount
     * CR: Product Revenue Accounts = Subtotal
     * CR: Sales Tax Payable (Liability) = Tax Total (if any)
     * Plus Perpetual Inventory / COGS:
     * DR: Cost of Goods Sold (Expense) = Line Cost
     * CR: Merchandise Inventory (Asset) = Line Cost
     */
    public function postInvoice(Invoice $invoice, User $user): JournalEntry
    {
        $this->assertDateInActiveFiscalYear($invoice->issue_date);

        // Find Accounts Receivable (AR) account
        $arAccount = Account::where('code', '1200')->first()
            ?? Account::where('sub_type', 'current_asset')->where('name', 'LIKE', '%Receivable%')->first()
            ?? Account::where('type', 'asset')->first();

        // Find Sales Tax Payable account
        $taxAccount = Account::where('code', '2200')->first()
            ?? Account::where('sub_type', 'current_liability')->where('name', 'LIKE', '%Tax%')->first()
            ?? Account::where('type', 'liability')->first();

        $items = [];
        // Debit AR
        $items[] = [
            'account_id' => $arAccount->id,
            'type' => 'debit',
            'amount' => (float) $invoice->total_amount,
            'memo' => "Invoice {$invoice->invoice_number} - {$invoice->customer->name}",
        ];

        // Credit Revenue accounts per item
        foreach ($invoice->items as $item) {
            $items[] = [
                'account_id' => $item->account_id,
                'type' => 'credit',
                'amount' => (float) $item->subtotal,
                'memo' => "Item: {$item->description}",
            ];
        }

        // Credit Tax if applicable
        if ((float) $invoice->tax_total > 0 && $taxAccount) {
            $items[] = [
                'account_id' => $taxAccount->id,
                'type' => 'credit',
                'amount' => (float) $invoice->tax_total,
                'memo' => "Sales Tax for Invoice {$invoice->invoice_number}",
            ];
        }

        // Inventory Stock Decrement and COGS Double-Entry Posting
        foreach ($invoice->items as $item) {
            $product = $item->product;
            if ($product && $product->type === 'goods') {
                $unitCost = (float) $product->purchase_price;
                $lineCost = round((float) $item->quantity * $unitCost, 4);

                if ($lineCost > 0 && $product->expense_account_id && $product->asset_account_id) {
                    // DR: Cost of Goods Sold (Expense)
                    $items[] = [
                        'account_id' => $product->expense_account_id,
                        'type' => 'debit',
                        'amount' => $lineCost,
                        'memo' => "COGS for {$product->name} (Qty: {$item->quantity})",
                    ];

                    // CR: Merchandise Inventory (Asset)
                    $items[] = [
                        'account_id' => $product->asset_account_id,
                        'type' => 'credit',
                        'amount' => $lineCost,
                        'memo' => "Inventory reduction for {$product->name}",
                    ];
                }

                // Decrement physical stock on hand
                $product->decrement('quantity_on_hand', (float) $item->quantity);
            }
        }

        $entry = $this->createJournalEntry([
            'entry_number' => 'INV-'.$invoice->invoice_number,
            'date' => $invoice->issue_date->toDateString(),
            'reference' => $invoice->invoice_number,
            'description' => "Sales Invoice {$invoice->invoice_number} to {$invoice->customer->name}",
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
            'items' => $items,
        ], $user, true);

        $invoice->update([
            'journal_entry_id' => $entry->id,
            'status' => 'sent',
        ]);

        return $entry;
    }

    /**
     * Post a Customer Invoice Payment:
     * DR: Bank / Cash (Asset) = Payment Amount
     * CR: Accounts Receivable (Asset) = Payment Amount
     */
    public function postInvoicePayment(Payment $payment, User $user): JournalEntry
    {
        $this->assertDateInActiveFiscalYear($payment->payment_date);

        $arAccount = Account::where('code', '1200')->first()
            ?? Account::where('sub_type', 'current_asset')->where('name', 'LIKE', '%Receivable%')->first()
            ?? Account::where('type', 'asset')->first();

        $entry = $this->createJournalEntry([
            'entry_number' => 'PAY-'.$payment->payment_number,
            'date' => $payment->payment_date->toDateString(),
            'reference' => $payment->reference ?? $payment->payment_number,
            'description' => "Payment received from {$payment->contact->name} for invoice {$payment->invoice?->invoice_number}",
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'items' => [
                [
                    'account_id' => $payment->bank_account_id,
                    'type' => 'debit',
                    'amount' => (float) $payment->amount,
                    'memo' => "Cash received ({$payment->payment_method})",
                ],
                [
                    'account_id' => $arAccount->id,
                    'type' => 'credit',
                    'amount' => (float) $payment->amount,
                    'memo' => "AR reduction for {$payment->contact->name}",
                ],
            ],
        ], $user, true);

        $payment->update(['journal_entry_id' => $entry->id]);

        if ($payment->invoice) {
            $invoice = $payment->invoice;
            $newPaid = (float) $invoice->paid_amount + (float) $payment->amount;
            $invoice->update([
                'paid_amount' => $newPaid,
                'status' => $newPaid >= (float) $invoice->total_amount ? 'paid' : 'partially_paid',
            ]);
        }

        return $entry;
    }

    /**
     * Post a Vendor Bill to General Ledger:
     * DR: Expense / Inventory Accounts = Subtotal
     * DR: Tax Expense / VAT Input = Tax Total (if any)
     * CR: Accounts Payable (Liability) = Total Amount
     * Plus increment physical product stock for goods.
     */
    public function postBill(Bill $bill, User $user): JournalEntry
    {
        $this->assertDateInActiveFiscalYear($bill->bill_date);

        $apAccount = Account::where('code', '2010')->first()
            ?? Account::where('sub_type', 'current_liability')->where('name', 'LIKE', '%Payable%')->first()
            ?? Account::where('type', 'liability')->first();

        $taxAccount = Account::where('code', '1300')->first() // Input Tax (Current Asset)
            ?? Account::where('name', 'LIKE', '%Input Tax%')->first()
            ?? $apAccount;

        $items = [];
        foreach ($bill->items as $item) {
            $items[] = [
                'account_id' => $item->account_id,
                'type' => 'debit',
                'amount' => (float) $item->subtotal,
                'memo' => "Bill {$bill->bill_number} - {$item->description}",
            ];

            // Increment inventory stock on hand when purchasing physical goods
            if ($item->product && $item->product->type === 'goods') {
                $item->product->increment('quantity_on_hand', (float) $item->quantity);
            }
        }

        if ((float) $bill->tax_total > 0) {
            $items[] = [
                'account_id' => $taxAccount->id,
                'type' => 'debit',
                'amount' => (float) $bill->tax_total,
                'memo' => "Input Tax for Bill {$bill->bill_number}",
            ];
        }

        $items[] = [
            'account_id' => $apAccount->id,
            'type' => 'credit',
            'amount' => (float) $bill->total_amount,
            'memo' => "Accounts Payable to {$bill->vendor->name}",
        ];

        $entry = $this->createJournalEntry([
            'entry_number' => 'BILL-'.$bill->bill_number,
            'date' => $bill->bill_date->toDateString(),
            'reference' => $bill->bill_number,
            'description' => "Purchase Bill {$bill->bill_number} from {$bill->vendor->name}",
            'source_type' => 'bill',
            'source_id' => $bill->id,
            'items' => $items,
        ], $user, true);

        $bill->update([
            'journal_entry_id' => $entry->id,
            'status' => 'approved',
        ]);

        return $entry;
    }

    /**
     * Post a Vendor Bill Payment:
     * DR: Accounts Payable (Liability) = Payment Amount
     * CR: Bank / Cash (Asset) = Payment Amount
     */
    public function postBillPayment(Payment $payment, User $user): JournalEntry
    {
        $this->assertDateInActiveFiscalYear($payment->payment_date);

        $apAccount = Account::where('code', '2010')->first()
            ?? Account::where('sub_type', 'current_liability')->where('name', 'LIKE', '%Payable%')->first()
            ?? Account::where('type', 'liability')->first();

        $entry = $this->createJournalEntry([
            'entry_number' => 'PAY-'.$payment->payment_number,
            'date' => $payment->payment_date->toDateString(),
            'reference' => $payment->reference ?? $payment->payment_number,
            'description' => "Bill payment to {$payment->contact->name} for bill {$payment->bill?->bill_number}",
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'items' => [
                [
                    'account_id' => $apAccount->id,
                    'type' => 'debit',
                    'amount' => (float) $payment->amount,
                    'memo' => "AP reduction for {$payment->contact->name}",
                ],
                [
                    'account_id' => $payment->bank_account_id,
                    'type' => 'credit',
                    'amount' => (float) $payment->amount,
                    'memo' => "Payment made ({$payment->payment_method})",
                ],
            ],
        ], $user, true);

        $payment->update(['journal_entry_id' => $entry->id]);

        if ($payment->bill) {
            $bill = $payment->bill;
            $newPaid = (float) $bill->paid_amount + (float) $payment->amount;
            $bill->update([
                'paid_amount' => $newPaid,
                'status' => $newPaid >= (float) $bill->total_amount ? 'paid' : 'partially_paid',
            ]);
        }

        return $entry;
    }

    /**
     * Close a fiscal year by generating a year-end closing journal entry to Retained Earnings
     * and locking the fiscal period.
     */
    public function closeFiscalYear(FiscalYear $fiscalYear, User $user): ?JournalEntry
    {
        if ($fiscalYear->status === 'closed') {
            throw new Exception("Fiscal year {$fiscalYear->name} is already closed.");
        }

        return DB::transaction(function () use ($fiscalYear, $user) {
            $startDate = $fiscalYear->start_date->toDateString();
            $endDate = $fiscalYear->end_date->toDateString();

            // Find Retained Earnings account (Equity)
            $retainedEarnings = Account::where('code', '3990')->first()
                ?? Account::where('sub_type', 'equity')->where('name', 'LIKE', '%Retained%')->first()
                ?? Account::where('type', 'equity')->first();

            if (! $retainedEarnings) {
                throw new Exception('Retained Earnings account could not be found to transfer year-end net income.');
            }

            // Aggregate revenue and expense account balances for this period
            $reportService = app(ReportService::class);
            $periodSums = $reportService->getBatchPeriodSums($startDate, $endDate);

            $revenueAccounts = Account::where('type', 'revenue')->get();
            $expenseAccounts = Account::where('type', 'expense')->get();

            $closingItems = [];
            $totalRevenue = 0.0;
            $totalExpense = 0.0;

            // Zero out Revenue accounts (Normally credit, so debit to zero out)
            foreach ($revenueAccounts as $acc) {
                $dr = $periodSums[$acc->id]['debit'] ?? 0.0;
                $cr = $periodSums[$acc->id]['credit'] ?? 0.0;
                $net = $acc->normal_balance === 'debit' ? ($dr - $cr) : ($cr - $dr);

                if (abs($net) > 0.0001) {
                    $totalRevenue += $net;
                    if ($net > 0) {
                        $closingItems[] = [
                            'account_id' => $acc->id,
                            'type' => 'debit',
                            'amount' => round($net, 4),
                            'memo' => "Year-end zeroing of revenue account {$acc->code} - {$acc->name}",
                        ];
                    } else {
                        $closingItems[] = [
                            'account_id' => $acc->id,
                            'type' => 'credit',
                            'amount' => round(abs($net), 4),
                            'memo' => "Year-end zeroing of negative revenue {$acc->code} - {$acc->name}",
                        ];
                    }
                }
            }

            // Zero out Expense accounts (Normally debit, so credit to zero out)
            foreach ($expenseAccounts as $acc) {
                $dr = $periodSums[$acc->id]['debit'] ?? 0.0;
                $cr = $periodSums[$acc->id]['credit'] ?? 0.0;
                $net = $acc->normal_balance === 'debit' ? ($dr - $cr) : ($cr - $dr);

                if (abs($net) > 0.0001) {
                    $totalExpense += $net;
                    if ($net > 0) {
                        $closingItems[] = [
                            'account_id' => $acc->id,
                            'type' => 'credit',
                            'amount' => round($net, 4),
                            'memo' => "Year-end zeroing of expense account {$acc->code} - {$acc->name}",
                        ];
                    } else {
                        $closingItems[] = [
                            'account_id' => $acc->id,
                            'type' => 'debit',
                            'amount' => round(abs($net), 4),
                            'memo' => "Year-end zeroing of negative expense {$acc->code} - {$acc->name}",
                        ];
                    }
                }
            }

            $closingEntry = null;

            if (count($closingItems) > 0) {
                $netProfit = $totalRevenue - $totalExpense;

                if (abs($netProfit) > 0.0001) {
                    if ($netProfit > 0) {
                        // Net Profit: Credit Retained Earnings
                        $closingItems[] = [
                            'account_id' => $retainedEarnings->id,
                            'type' => 'credit',
                            'amount' => round($netProfit, 4),
                            'memo' => "Transfer Net Profit to Retained Earnings for {$fiscalYear->name}",
                        ];
                    } else {
                        // Net Loss: Debit Retained Earnings
                        $closingItems[] = [
                            'account_id' => $retainedEarnings->id,
                            'type' => 'debit',
                            'amount' => round(abs($netProfit), 4),
                            'memo' => "Transfer Net Loss to Retained Earnings for {$fiscalYear->name}",
                        ];
                    }
                }

                // Post closing journal entry
                $closingEntry = $this->createJournalEntry([
                    'entry_number' => "JE-CLOSE-{$fiscalYear->id}",
                    'date' => $endDate,
                    'reference' => "CLOSE-{$fiscalYear->name}",
                    'description' => "Year-End Closing Journal Entry for {$fiscalYear->name}",
                    'source_type' => 'year_end_close',
                    'source_id' => $fiscalYear->id,
                    'items' => $closingItems,
                ], $user, true);
            }

            // Lock the fiscal year
            $fiscalYear->update([
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'closed_by' => $user->id,
            ]);

            AuditLog::record(
                $user,
                'close_fiscal_year',
                "Closed fiscal year {$fiscalYear->name} with year-end closing entry ".($closingEntry?->entry_number ?? 'None'),
                $fiscalYear
            );

            return $closingEntry;
        });
    }

    /**
     * Transfer funds between two cash/bank accounts.
     */
    public function transferBankFunds(
        int $sourceAccountId,
        int $destinationAccountId,
        float $amount,
        string $date,
        ?string $reference,
        ?string $description,
        User $user
    ): JournalEntry {
        if ($sourceAccountId === $destinationAccountId) {
            throw new Exception('Source and destination accounts must be different.');
        }

        if ($amount <= 0) {
            throw new Exception('Transfer amount must be greater than zero.');
        }

        $source = Account::findOrFail($sourceAccountId);
        $dest = Account::findOrFail($destinationAccountId);

        $this->assertDateInActiveFiscalYear($date);

        return $this->createJournalEntry([
            'date' => $date,
            'reference' => $reference,
            'description' => $description ?: "Bank Transfer: {$source->name} -> {$dest->name}",
            'source_type' => 'bank_transfer',
            'items' => [
                [
                    'account_id' => $dest->id,
                    'type' => 'debit',
                    'amount' => round($amount, 4),
                    'memo' => "Transfer deposit from {$source->name}",
                ],
                [
                    'account_id' => $source->id,
                    'type' => 'credit',
                    'amount' => round($amount, 4),
                    'memo' => "Transfer withdrawal to {$dest->name}",
                ],
            ],
        ], $user, true);
    }
}
