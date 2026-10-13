<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Contact;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ReportService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected AccountingService $accountingService;

    protected ReportService $reportService;

    protected Account $cashAccount;

    protected Account $capitalAccount;

    protected Account $arAccount;

    protected Account $revenueAccount;

    protected Account $apAccount;

    protected Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);
        $this->reportService = app(ReportService::class);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        FiscalYear::create([
            'name' => 'FY '.Carbon::now()->year,
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'status' => 'active',
        ]);

        $this->cashAccount = Account::create([
            'code' => '1020',
            'name' => 'Operating Cash & Bank',
            'type' => 'asset',
            'sub_type' => 'current_asset',
            'normal_balance' => 'debit',
        ]);

        $this->arAccount = Account::create([
            'code' => '1200',
            'name' => 'Accounts Receivable',
            'type' => 'asset',
            'sub_type' => 'current_asset',
            'normal_balance' => 'debit',
            'is_system' => true,
        ]);

        $this->capitalAccount = Account::create([
            'code' => '3010',
            'name' => 'Owner Capital',
            'type' => 'equity',
            'sub_type' => 'equity',
            'normal_balance' => 'credit',
        ]);

        $this->apAccount = Account::create([
            'code' => '2010',
            'name' => 'Accounts Payable',
            'type' => 'liability',
            'sub_type' => 'current_liability',
            'normal_balance' => 'credit',
            'is_system' => true,
        ]);

        $this->revenueAccount = Account::create([
            'code' => '4010',
            'name' => 'Consulting Revenue',
            'type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'normal_balance' => 'credit',
        ]);

        $this->expenseAccount = Account::create([
            'code' => '6010',
            'name' => 'Operating Expense',
            'type' => 'expense',
            'sub_type' => 'operating_expense',
            'normal_balance' => 'debit',
        ]);
    }

    public function test_unbalanced_journal_entry_is_rejected(): void
    {
        $this->expectException(Exception::class);

        $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Unbalanced test',
            'items' => [
                [
                    'account_id' => $this->cashAccount->id,
                    'type' => 'debit',
                    'amount' => 1000.00,
                ],
                [
                    'account_id' => $this->capitalAccount->id,
                    'type' => 'credit',
                    'amount' => 800.00, // Difference of 200.00
                ],
            ],
        ], $this->admin);
    }

    public function test_balanced_journal_entry_posts_and_updates_balances(): void
    {
        $entry = $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Capital Infusion',
            'items' => [
                [
                    'account_id' => $this->cashAccount->id,
                    'type' => 'debit',
                    'amount' => 5000.00,
                ],
                [
                    'account_id' => $this->capitalAccount->id,
                    'type' => 'credit',
                    'amount' => 5000.00,
                ],
            ],
        ], $this->admin, true);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'status' => 'posted',
            'total_debit' => 5000.00,
            'total_credit' => 5000.00,
        ]);

        $this->assertEquals(5000.00, $this->cashAccount->balance);
        $this->assertEquals(5000.00, $this->capitalAccount->balance);
    }

    public function test_sales_invoice_creates_and_posts_ar_journal(): void
    {
        $customer = Contact::create([
            'type' => 'customer',
            'name' => 'Acme Test Corp',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'customer_id' => $customer->id,
            'issue_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => 2000.00,
            'tax_total' => 0,
            'discount_total' => 0,
            'total_amount' => 2000.00,
            'paid_amount' => 0,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $invoice->items()->create([
            'account_id' => $this->revenueAccount->id,
            'description' => 'Test Consulting',
            'quantity' => 10,
            'unit_price' => 200.00,
            'tax_percent' => 0,
            'discount_percent' => 0,
            'subtotal' => 2000.00,
            'tax_amount' => 0,
            'total' => 2000.00,
        ]);

        $this->accountingService->postInvoice($invoice, $this->admin);

        $this->assertEquals('sent', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->journal_entry_id);
        $this->assertEquals(2000.00, $this->arAccount->balance);
        $this->assertEquals(2000.00, $this->revenueAccount->balance);
    }

    public function test_trial_balance_and_balance_sheet_are_in_equilibrium(): void
    {
        // 1. Capital infusion
        $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Capital',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'type' => 'debit', 'amount' => 10000.00],
                ['account_id' => $this->capitalAccount->id, 'type' => 'credit', 'amount' => 10000.00],
            ],
        ], $this->admin, true);

        // 2. Rent expense
        $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Rent Expense',
            'items' => [
                ['account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => 1500.00],
                ['account_id' => $this->cashAccount->id, 'type' => 'credit', 'amount' => 1500.00],
            ],
        ], $this->admin, true);

        $tb = $this->reportService->getTrialBalance();
        $this->assertTrue($tb['is_balanced'], 'Trial Balance must be balanced');
        $this->assertEquals(0.0, $tb['difference']);

        $bs = $this->reportService->getBalanceSheet();
        $this->assertTrue($bs['is_balanced'], 'Balance sheet Assets must equal Liabilities + Equity');
    }
}
