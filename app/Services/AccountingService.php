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
     * Generate sequential entry number: JE-YYYYMM-XXXX
     */
    public function generateEntryNumber(string $prefix = 'JE'): string
    {
        $ym = Carbon::now()->format('Ym');
        $latest = JournalEntry::where('entry_number', 'LIKE', "{$prefix}-{$ym}-%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($latest) {
            $parts = explode('-', $latest->entry_number);
            $seq = ((int) end($parts)) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $ym, $seq);
    }

    /**
     * Check if date falls in an active fiscal year.
     */
    public function assertDateInActiveFiscalYear(string|Carbon $date): void
    {
        $d = Carbon::parse($date)->toDateString();
        $closed = FiscalYear::where('status', 'closed')
            ->where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->exists();

        if ($closed) {
            throw new Exception("Accounting operations are locked for closed fiscal period on {$d}.");
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
}
