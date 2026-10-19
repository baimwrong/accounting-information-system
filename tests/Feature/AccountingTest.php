<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Contact;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
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

    public function test_perpetual_inventory_cogs_and_stock_decrement_on_invoice_posting(): void
    {
        $inventoryAccount = Account::create([
            'code' => '1300',
            'name' => 'Merchandise Inventory',
            'type' => 'asset',
            'sub_type' => 'current_asset',
            'normal_balance' => 'debit',
        ]);

        $cogsAccount = Account::create([
            'code' => '5000',
            'name' => 'Cost of Goods Sold',
            'type' => 'expense',
            'sub_type' => 'cost_of_goods_sold',
            'normal_balance' => 'debit',
        ]);

        $product = Product::create([
            'code' => 'WIDGET-01',
            'name' => 'Industrial Widget',
            'type' => 'goods',
            'income_account_id' => $this->revenueAccount->id,
            'expense_account_id' => $cogsAccount->id,
            'asset_account_id' => $inventoryAccount->id,
            'purchase_price' => 40.00,
            'sale_price' => 100.00,
            'quantity_on_hand' => 50,
            'is_active' => true,
        ]);

        $customer = Contact::create([
            'type' => 'customer',
            'name' => 'Beta Client',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-INV-001',
            'customer_id' => $customer->id,
            'issue_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => 500.00,
            'tax_total' => 0,
            'discount_total' => 0,
            'total_amount' => 500.00,
            'paid_amount' => 0,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'account_id' => $this->revenueAccount->id,
            'description' => 'Industrial Widget Sale',
            'quantity' => 5,
            'unit_price' => 100.00,
            'subtotal' => 500.00,
            'tax_amount' => 0,
            'total' => 500.00,
        ]);

        $entry = $this->accountingService->postInvoice($invoice, $this->admin);

        // Verify stock decremented
        $this->assertEquals(45, (float) $product->fresh()->quantity_on_hand);

        // Verify COGS debit and Inventory credit
        $this->assertDatabaseHas('journal_entry_items', [
            'journal_entry_id' => $entry->id,
            'account_id' => $cogsAccount->id,
            'type' => 'debit',
            'amount' => 200.00,
        ]);

        $this->assertDatabaseHas('journal_entry_items', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inventoryAccount->id,
            'type' => 'credit',
            'amount' => 200.00,
        ]);

        $this->assertEquals(200.00, $cogsAccount->balance);
        $this->assertEquals(-200.00, $inventoryAccount->balance);
    }

    public function test_bill_posting_increments_inventory_stock(): void
    {
        $product = Product::create([
            'code' => 'PART-A',
            'name' => 'Spare Part A',
            'type' => 'goods',
            'income_account_id' => $this->revenueAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
            'purchase_price' => 30.00,
            'sale_price' => 60.00,
            'quantity_on_hand' => 10,
            'is_active' => true,
        ]);

        $vendor = Contact::create([
            'type' => 'vendor',
            'name' => 'Supplier Global',
        ]);

        $bill = Bill::create([
            'bill_number' => 'BILL-TEST-001',
            'vendor_id' => $vendor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => 600.00,
            'tax_total' => 0,
            'total_amount' => 600.00,
            'paid_amount' => 0,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $bill->items()->create([
            'product_id' => $product->id,
            'account_id' => $this->expenseAccount->id,
            'description' => '20 Spare Parts',
            'quantity' => 20,
            'unit_price' => 30.00,
            'subtotal' => 600.00,
            'tax_amount' => 0,
            'total' => 600.00,
        ]);

        $this->accountingService->postBill($bill, $this->admin);

        $this->assertEquals(30, (float) $product->fresh()->quantity_on_hand);
        $this->assertEquals('approved', $bill->fresh()->status);
    }

    public function test_strict_fiscal_year_validation_rejects_dates_outside_active_periods(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No active fiscal period found covering');

        $this->accountingService->createJournalEntry([
            'date' => '1999-01-01',
            'description' => 'Out of period entry',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'type' => 'debit', 'amount' => 100.00],
                ['account_id' => $this->capitalAccount->id, 'type' => 'credit', 'amount' => 100.00],
            ],
        ], $this->admin);
    }

    public function test_year_end_close_zeros_revenue_and_expense_into_retained_earnings(): void
    {
        $retainedEarnings = Account::create([
            'code' => '3990',
            'name' => 'Retained Earnings',
            'type' => 'equity',
            'sub_type' => 'equity',
            'normal_balance' => 'credit',
        ]);

        // Post revenue: $8,000
        $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Sales',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'type' => 'debit', 'amount' => 8000.00],
                ['account_id' => $this->revenueAccount->id, 'type' => 'credit', 'amount' => 8000.00],
            ],
        ], $this->admin, true);

        // Post expense: $3,000
        $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Utilities',
            'items' => [
                ['account_id' => $this->expenseAccount->id, 'type' => 'debit', 'amount' => 3000.00],
                ['account_id' => $this->cashAccount->id, 'type' => 'credit', 'amount' => 3000.00],
            ],
        ], $this->admin, true);

        $this->assertEquals(8000.00, $this->revenueAccount->balance);
        $this->assertEquals(3000.00, $this->expenseAccount->balance);

        $fiscalYear = FiscalYear::where('status', 'active')->firstOrFail();
        $closingEntry = $this->accountingService->closeFiscalYear($fiscalYear, $this->admin);

        $this->assertNotNull($closingEntry);
        $this->assertEquals('closed', $fiscalYear->fresh()->status);

        // Verify revenue and expense are zeroed out
        $this->assertEquals(0.00, $this->revenueAccount->balance);
        $this->assertEquals(0.00, $this->expenseAccount->balance);

        // Verify net profit ($5,000) was credited to Retained Earnings
        $this->assertEquals(5000.00, $retainedEarnings->balance);
    }

    public function test_inter_bank_transfer_creates_balanced_double_entry(): void
    {
        $savings = Account::create([
            'code' => '1030',
            'name' => 'High Yield Savings',
            'type' => 'asset',
            'sub_type' => 'current_asset',
            'normal_balance' => 'debit',
        ]);

        // Fund checking account with $5,000
        $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Initial checking balance',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'type' => 'debit', 'amount' => 5000.00],
                ['account_id' => $this->capitalAccount->id, 'type' => 'credit', 'amount' => 5000.00],
            ],
        ], $this->admin, true);

        // Transfer $2,000 from Checking to Savings
        $entry = $this->accountingService->transferBankFunds(
            $this->cashAccount->id,
            $savings->id,
            2000.00,
            Carbon::now()->toDateString(),
            'WIRE-001',
            'Transfer to savings',
            $this->admin
        );

        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(3000.00, $this->cashAccount->balance);
        $this->assertEquals(2000.00, $savings->balance);
    }

    public function test_ar_and_ap_aging_reports(): void
    {
        $customer = Contact::create(['type' => 'customer', 'name' => 'Aging Customer Corp']);

        // Current invoice ($500) due in 10 days
        Invoice::create([
            'invoice_number' => 'INV-CURR',
            'customer_id' => $customer->id,
            'issue_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(10)->toDateString(),
            'subtotal' => 500.00,
            'tax_total' => 0,
            'discount_total' => 0,
            'total_amount' => 500.00,
            'paid_amount' => 0,
            'status' => 'sent',
            'created_by' => $this->admin->id,
        ]);

        // Overdue invoice ($1,000) due 45 days ago (bucket 31-60)
        Invoice::create([
            'invoice_number' => 'INV-OVERDUE',
            'customer_id' => $customer->id,
            'issue_date' => Carbon::now()->subDays(60)->toDateString(),
            'due_date' => Carbon::now()->subDays(45)->toDateString(),
            'subtotal' => 1000.00,
            'tax_total' => 0,
            'discount_total' => 0,
            'total_amount' => 1000.00,
            'paid_amount' => 0,
            'status' => 'overdue',
            'created_by' => $this->admin->id,
        ]);

        $arReport = $this->reportService->getArAgingReport(Carbon::now()->toDateString());
        $this->assertEquals(1500.00, $arReport['totals']['total']);
        $this->assertEquals(500.00, $arReport['totals']['current']);
        $this->assertEquals(1000.00, $arReport['totals']['days_31_60']);
    }

    public function test_statement_of_cash_flows_and_csv_export(): void
    {
        // Capital infusion ($10,000)
        $this->accountingService->createJournalEntry([
            'date' => Carbon::now()->toDateString(),
            'description' => 'Owner Capital',
            'items' => [
                ['account_id' => $this->cashAccount->id, 'type' => 'debit', 'amount' => 10000.00],
                ['account_id' => $this->capitalAccount->id, 'type' => 'credit', 'amount' => 10000.00],
            ],
        ], $this->admin, true);

        $start = Carbon::now()->startOfYear()->toDateString();
        $end = Carbon::now()->toDateString();

        $cf = $this->reportService->getCashFlowStatement($start, $end);
        $this->assertArrayHasKey('operating_cash_flow', $cf);
        $this->assertArrayHasKey('net_cash_change', $cf);
        $this->assertArrayHasKey('ending_cash', $cf);

        $response = $this->reportService->exportToCsv('cash_flow', $cf);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_auditor_role_cannot_perform_modifications(): void
    {
        $auditor = User::factory()->create([
            'role' => 'auditor',
            'is_active' => true,
        ]);

        // Attempt to create contact
        $response = $this->actingAs($auditor)->post(route('contacts.store'), [
            'type' => 'customer',
            'name' => 'Auditor Unauthorized Customer',
        ]);
        $response->assertForbidden();

        // Attempt bank transfer
        $response = $this->actingAs($auditor)->post(route('payments.transfer'), [
            'source_account_id' => $this->cashAccount->id,
            'destination_account_id' => $this->capitalAccount->id,
            'amount' => 100.00,
            'date' => Carbon::now()->toDateString(),
        ]);
        $response->assertForbidden();
    }
}
