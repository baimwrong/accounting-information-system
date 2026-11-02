<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\Contact;
use App\Models\FiscalYear;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $accountant;

    protected Account $bankAccount;

    protected Account $arAccount;

    protected Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->bankAccount = Account::create([
            'code' => '1020',
            'name' => 'Operating Bank Account',
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

        $this->customer = Contact::create([
            'name' => 'Global Logistics LLC',
            'type' => 'customer',
            'is_active' => true,
        ]);
    }

    public function test_security_headers_are_attached_to_responses(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_bank_reconciliation_workflow(): void
    {
        // 1. Create payments into the bank account
        $payment1 = Payment::create([
            'payment_number' => 'REC-TEST-001',
            'type' => 'received',
            'contact_id' => $this->customer->id,
            'bank_account_id' => $this->bankAccount->id,
            'payment_date' => Carbon::now()->subDays(5)->toDateString(),
            'amount' => 500.00,
            'payment_method' => 'Bank Transfer',
            'created_by' => $this->accountant->id,
        ]);

        $payment2 = Payment::create([
            'payment_number' => 'DIS-TEST-001',
            'type' => 'sent',
            'contact_id' => $this->customer->id,
            'bank_account_id' => $this->bankAccount->id,
            'payment_date' => Carbon::now()->subDays(3)->toDateString(),
            'amount' => 200.00,
            'payment_method' => 'Bank Wire',
            'created_by' => $this->accountant->id,
        ]);

        // Net expected bank balance: +500 - 200 = $300.00
        // 2. Open Bank Reconciliation session with Statement Balance = 300.00
        $response = $this->actingAs($this->accountant)->post(route('bank-reconciliations.store'), [
            'bank_account_id' => $this->bankAccount->id,
            'statement_date' => Carbon::now()->toDateString(),
            'statement_balance' => 300.00,
            'notes' => 'Month End Reconciliation',
        ]);

        $reconciliation = BankReconciliation::first();
        $this->assertNotNull($reconciliation);
        $response->assertRedirect(route('bank-reconciliations.show', $reconciliation));
        $this->assertEquals('draft', $reconciliation->status);

        // 3. Clear payment 1 (+500)
        $this->actingAs($this->accountant)->post(route('bank-reconciliations.toggle', [$reconciliation, $payment1]));
        $payment1->refresh();
        $this->assertEquals($reconciliation->id, $payment1->bank_reconciliation_id);
        $this->assertTrue($payment1->isReconciled());

        // At this point: cleared = 500, statement = 300, difference = -200 -> cannot complete
        $this->actingAs($this->accountant)->get(route('bank-reconciliations.show', $reconciliation));
        $reconciliation->refresh();
        $this->assertEquals(500.00, (float) $reconciliation->reconciled_balance);
        $this->assertEquals(-200.00, (float) $reconciliation->difference);

        $failCompleteResponse = $this->actingAs($this->accountant)->post(route('bank-reconciliations.complete', $reconciliation));
        $failCompleteResponse->assertSessionHas('error');
        $this->assertEquals('draft', $reconciliation->fresh()->status);

        // 4. Clear payment 2 (-200) -> Net cleared becomes 300, difference = 0.00
        $this->actingAs($this->accountant)->post(route('bank-reconciliations.toggle', [$reconciliation, $payment2]));
        $this->actingAs($this->accountant)->get(route('bank-reconciliations.show', $reconciliation));
        $reconciliation->refresh();
        $this->assertEquals(300.00, (float) $reconciliation->reconciled_balance);
        $this->assertEquals(0.00, (float) $reconciliation->difference);

        // 5. Complete and Lock reconciliation
        $completeResponse = $this->actingAs($this->accountant)->post(route('bank-reconciliations.complete', $reconciliation));
        $completeResponse->assertSessionHas('success');

        $reconciliation->refresh();
        $this->assertTrue($reconciliation->isCompleted());
        $this->assertEquals($this->accountant->id, $reconciliation->completed_by);
        $this->assertNotNull($reconciliation->completed_at);
    }

    public function test_admin_can_trigger_database_backup_and_download(): void
    {
        $response = $this->actingAs($this->admin)->post(route('backups.store'));
        $response->assertRedirect(route('backups.index'));
        $response->assertSessionHas('success');

        $backupDir = storage_path('app/backups');
        $this->assertTrue(File::isDirectory($backupDir));

        $files = File::files($backupDir);
        $this->assertNotEmpty($files);

        $latestFile = $files[0]->getFilename();

        // Download test
        $downloadResponse = $this->actingAs($this->admin)->get(route('backups.download', $latestFile));
        $downloadResponse->assertStatus(200);

        // Delete test
        $deleteResponse = $this->actingAs($this->admin)->delete(route('backups.destroy', $latestFile));
        $deleteResponse->assertRedirect(route('backups.index'));
        $this->assertFalse(File::exists("{$backupDir}/{$latestFile}"));
    }

    public function test_non_admin_cannot_access_backups(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('backups.index'));
        $response->assertStatus(403);
    }
}
