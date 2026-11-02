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
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $accountant;

    protected AccountingService $accountingService;

    protected Account $cashAccount;

    protected Account $arAccount;

    protected Account $apAccount;

    protected Account $revenueAccount;

    protected Account $expenseAccount;

    protected Account $inventoryAccount;

    protected Contact $customer;

    protected Contact $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->accountant = User::factory()->create([
            'role' => 'accountant',
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
            'name' => 'Product Revenue',
            'type' => 'revenue',
            'sub_type' => 'operating_revenue',
            'normal_balance' => 'credit',
        ]);

        $this->expenseAccount = Account::create([
            'code' => '5010',
            'name' => 'Cost of Goods Sold',
            'type' => 'expense',
            'sub_type' => 'cost_of_goods_sold',
            'normal_balance' => 'debit',
        ]);

        $this->inventoryAccount = Account::create([
            'code' => '1400',
            'name' => 'Merchandise Inventory',
            'type' => 'asset',
            'sub_type' => 'current_asset',
            'normal_balance' => 'debit',
        ]);

        $this->customer = Contact::create([
            'name' => 'Alpha Client',
            'type' => 'customer',
            'is_active' => true,
        ]);

        $this->vendor = Contact::create([
            'name' => 'Beta Supplier',
            'type' => 'vendor',
            'is_active' => true,
        ]);
    }

    public function test_post_bill_updates_moving_average_cost_and_inventory_stock(): void
    {
        // Initial product: 10 units @ $100 = $1,000
        $product = Product::create([
            'code' => 'SRV-001',
            'name' => 'Rackmount Server',
            'type' => 'goods',
            'income_account_id' => $this->revenueAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->inventoryAccount->id,
            'sale_price' => 200.00,
            'purchase_price' => 100.00,
            'quantity_on_hand' => 10.00,
        ]);

        // Purchase bill: 10 more units @ $120.00 each
        $bill = Bill::create([
            'bill_number' => 'BILL-TEST-001',
            'vendor_id' => $this->vendor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => 1200.00,
            'tax_total' => 0.00,
            'total_amount' => 1200.00,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $bill->items()->create([
            'product_id' => $product->id,
            'account_id' => $this->inventoryAccount->id,
            'description' => '10 Servers @ 120',
            'quantity' => 10.00,
            'unit_price' => 120.00,
            'subtotal' => 1200.00,
            'total' => 1200.00,
        ]);

        $this->accountingService->postBill($bill, $this->admin);

        $product->refresh();
        // New quantity: 10 + 10 = 20
        $this->assertEquals(20.00, (float) $product->quantity_on_hand);
        // Weighted Average Cost: ((10 * 100) + (10 * 120)) / 20 = 2200 / 20 = 110.00
        $this->assertEquals(110.00, (float) $product->purchase_price);
    }

    public function test_void_invoice_reverses_general_ledger_and_restores_inventory(): void
    {
        $product = Product::create([
            'code' => 'PRD-VOID',
            'name' => 'Physical Widget',
            'type' => 'goods',
            'income_account_id' => $this->revenueAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->inventoryAccount->id,
            'sale_price' => 150.00,
            'purchase_price' => 70.00,
            'quantity_on_hand' => 50.00,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-VOID-1',
            'customer_id' => $this->customer->id,
            'issue_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(15)->toDateString(),
            'subtotal' => 750.00,
            'tax_total' => 0.00,
            'total_amount' => 750.00,
            'paid_amount' => 0,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'account_id' => $this->revenueAccount->id,
            'description' => '5 Widgets @ 150',
            'quantity' => 5.00,
            'unit_price' => 150.00,
            'subtotal' => 750.00,
            'total' => 750.00,
        ]);

        $entry = $this->accountingService->postInvoice($invoice, $this->admin);

        $product->refresh();
        $this->assertEquals(45.00, (float) $product->quantity_on_hand);
        $this->assertEquals('posted', $entry->status);

        // Now void the invoice
        $this->accountingService->voidInvoice($invoice, $this->admin, 'Client cancelled order');

        $invoice->refresh();
        $entry->refresh();
        $product->refresh();

        $this->assertEquals('voided', $invoice->status);
        $this->assertEquals('voided', $entry->status);
        // Physical inventory should be restored back to 50
        $this->assertEquals(50.00, (float) $product->quantity_on_hand);
    }

    public function test_cannot_void_invoice_with_recorded_payments(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-VOID-2',
            'customer_id' => $this->customer->id,
            'issue_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(15)->toDateString(),
            'subtotal' => 500.00,
            'tax_total' => 0.00,
            'total_amount' => 500.00,
            'paid_amount' => 200.00, // partially paid
            'status' => 'partially_paid',
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(Exception::class);
        $this->accountingService->voidInvoice($invoice, $this->admin, 'Attempting void with payment');
    }

    public function test_void_bill_reverses_general_ledger_and_deducts_inventory(): void
    {
        $product = Product::create([
            'code' => 'PRD-BILL-VOID',
            'name' => 'Supplier Item',
            'type' => 'goods',
            'income_account_id' => $this->revenueAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->inventoryAccount->id,
            'sale_price' => 80.00,
            'purchase_price' => 40.00,
            'quantity_on_hand' => 10.00,
        ]);

        $bill = Bill::create([
            'bill_number' => 'BILL-VOID-001',
            'vendor_id' => $this->vendor->id,
            'bill_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => 400.00,
            'tax_total' => 0.00,
            'total_amount' => 400.00,
            'paid_amount' => 0,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $bill->items()->create([
            'product_id' => $product->id,
            'account_id' => $this->inventoryAccount->id,
            'description' => '10 units @ 40',
            'quantity' => 10.00,
            'unit_price' => 40.00,
            'subtotal' => 400.00,
            'total' => 400.00,
        ]);

        $entry = $this->accountingService->postBill($bill, $this->admin);

        $product->refresh();
        $this->assertEquals(20.00, (float) $product->quantity_on_hand);

        // Void the bill
        $this->accountingService->voidBill($bill, $this->admin, 'Wrong supplier invoice');

        $bill->refresh();
        $entry->refresh();
        $product->refresh();

        $this->assertEquals('voided', $bill->status);
        $this->assertEquals('voided', $entry->status);
        // Quantity should be decremented back to 10
        $this->assertEquals(10.00, (float) $product->quantity_on_hand);
    }

    public function test_overlapping_fiscal_year_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('fiscal-years.store'), [
            'name' => 'FY Overlap Test',
            'start_date' => Carbon::now()->startOfYear()->addMonths(2)->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->addMonths(2)->toDateString(),
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_admin_can_manage_users_and_reset_passwords(): void
    {
        // Create user
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Alice Auditor',
            'email' => 'alice@example.com',
            'role' => 'auditor',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('users.index'));
        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('auditor', $user->role);

        // Edit user role to accountant
        $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => 'Alice Accountant',
            'email' => 'alice@example.com',
            'role' => 'accountant',
            'is_active' => true,
        ]);

        $user->refresh();
        $this->assertEquals('accountant', $user->role);

        // Admin resets password
        $this->actingAs($this->admin)->post(route('users.reset-password', $user), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        // Soft delete user
        $this->actingAs($this->admin)->delete(route('users.destroy', $user));
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // Restore user
        $this->actingAs($this->admin)->post(route('users.restore', $user->id));
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_demote_or_deactivate_self(): void
    {
        $response = $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'auditor', // Attempt to demote self
            'is_active' => true,
        ]);

        $response->assertSessionHas('error');
        $this->admin->refresh();
        $this->assertEquals('admin', $this->admin->role);

        $deleteResponse = $this->actingAs($this->admin)->delete(route('users.destroy', $this->admin));
        $deleteResponse->assertSessionHas('error');
        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('users.index'));
        $response->assertStatus(403);
    }
}
