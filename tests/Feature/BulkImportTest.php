<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\BulkImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $accountant;

    protected BulkImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importService = app(BulkImportService::class);

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
    }

    public function test_non_admin_cannot_access_import_portal(): void
    {
        $response = $this->actingAs($this->accountant)->get(route('imports.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_download_sample_templates(): void
    {
        $response = $this->actingAs($this->admin)->get(route('imports.template', 'chart_of_accounts'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename="template_chart_of_accounts.csv"');
    }

    public function test_bulk_import_chart_of_accounts_from_csv(): void
    {
        $csvContent = "code,name,type,sub_type,normal_balance,parent_code,description\n"
            ."1015,Branch Petty Cash,asset,current_asset,debit,,Daily branch fund\n"
            ."4050,Software Licensing,revenue,operating_revenue,credit,,License fees\n";

        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csvContent);

        $batch = $this->importService->importAccounts($file, $this->admin);

        $this->assertEquals(2, $batch->total_rows);
        $this->assertEquals(2, $batch->successful_rows);
        $this->assertEquals(0, $batch->failed_rows);
        $this->assertEquals('completed', $batch->status);

        $this->assertDatabaseHas('accounts', ['code' => '1015', 'name' => 'Branch Petty Cash']);
        $this->assertDatabaseHas('accounts', ['code' => '4050', 'name' => 'Software Licensing']);
    }

    public function test_bulk_import_journal_entries_with_balance_validation(): void
    {
        $bank = Account::create(['code' => '1020', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit']);
        $capital = Account::create(['code' => '3010', 'name' => 'Capital', 'type' => 'equity', 'normal_balance' => 'credit']);

        $csvContent = "entry_number,date,reference,description,account_code,debit,credit,memo\n"
            .'JE-IMP-01,'.Carbon::now()->toDateString().",REF-1,Capital Import,1020,25000.00,0.00,Debit line\n"
            .'JE-IMP-01,'.Carbon::now()->toDateString().",REF-1,Capital Import,3010,0.00,25000.00,Credit line\n";

        $file = UploadedFile::fake()->createWithContent('journals.csv', $csvContent);

        $batch = $this->importService->importJournalEntries($file, $this->admin);

        $this->assertEquals(1, $batch->total_rows);
        $this->assertEquals(1, $batch->successful_rows);
        $this->assertEquals(0, $batch->failed_rows);
        $this->assertEquals('completed', $batch->status);

        $this->assertDatabaseHas('journal_entries', ['entry_number' => 'JE-IMP-01', 'status' => 'posted']);
        $this->assertEquals(25000.00, $bank->fresh()->balance);
    }
}
