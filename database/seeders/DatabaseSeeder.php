<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Contact;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accountingService = app(AccountingService::class);

        // 1. Core System Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Chief Executive Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $accountant = User::firstOrCreate(
            ['email' => 'accountant@example.com'],
            [
                'name' => 'Senior Corporate Accountant',
                'password' => Hash::make('password'),
                'role' => 'accountant',
                'is_active' => true,
            ]
        );

        $auditor = User::firstOrCreate(
            ['email' => 'auditor@example.com'],
            [
                'name' => 'External Compliance Auditor',
                'password' => Hash::make('password'),
                'role' => 'auditor',
                'is_active' => true,
            ]
        );

        // 2. Active Fiscal Year
        $currentYear = Carbon::now()->year;
        $fiscalYear = FiscalYear::firstOrCreate(
            ['name' => "FY {$currentYear}"],
            [
                'start_date' => Carbon::createFromDate($currentYear, 1, 1)->toDateString(),
                'end_date' => Carbon::createFromDate($currentYear, 12, 31)->toDateString(),
                'status' => 'active',
            ]
        );

        // 3. Master Chart of Accounts (COA)
        $coaList = [
            // ASSETS (1000 - 1999)
            ['code' => '1010', 'name' => 'Petty Cash', 'type' => 'asset', 'sub_type' => 'current_asset', 'normal_balance' => 'debit', 'description' => 'Daily operational cash fund'],
            ['code' => '1020', 'name' => 'Operating Bank Account', 'type' => 'asset', 'sub_type' => 'current_asset', 'normal_balance' => 'debit', 'description' => 'Primary commercial bank account for operating liquidity'],
            ['code' => '1030', 'name' => 'Treasury Money Market', 'type' => 'asset', 'sub_type' => 'current_asset', 'normal_balance' => 'debit', 'description' => 'Short term interest-bearing cash reserves'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'sub_type' => 'current_asset', 'normal_balance' => 'debit', 'is_system' => true, 'description' => 'Customer trade receivables control account'],
            ['code' => '1300', 'name' => 'Input VAT / Tax Receivable', 'type' => 'asset', 'sub_type' => 'current_asset', 'normal_balance' => 'debit', 'description' => 'Input taxes paid on vendor procurement'],
            ['code' => '1400', 'name' => 'Merchandise Inventory', 'type' => 'asset', 'sub_type' => 'current_asset', 'normal_balance' => 'debit', 'description' => 'Physical goods and hardware inventory on hand'],
            ['code' => '1500', 'name' => 'Office Hardware & Servers', 'type' => 'asset', 'sub_type' => 'non_current_asset', 'normal_balance' => 'debit', 'description' => 'Capital equipment and datacenter fixtures'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation - Hardware', 'type' => 'asset', 'sub_type' => 'non_current_asset', 'normal_balance' => 'credit', 'description' => 'Contra-asset account for accumulated equipment wear'],

            // LIABILITIES (2000 - 2999)
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => 'liability', 'sub_type' => 'current_liability', 'normal_balance' => 'credit', 'is_system' => true, 'description' => 'Trade liabilities owed to commercial vendors'],
            ['code' => '2200', 'name' => 'Sales Tax Payable / Output VAT', 'type' => 'liability', 'sub_type' => 'current_liability', 'normal_balance' => 'credit', 'is_system' => true, 'description' => 'Collected sales tax owed to tax authority'],
            ['code' => '2300', 'name' => 'Accrued Salaries & Payroll', 'type' => 'liability', 'sub_type' => 'current_liability', 'normal_balance' => 'credit', 'description' => 'Accrued wages payable to personnel'],
            ['code' => '2500', 'name' => 'Commercial Credit Facility', 'type' => 'liability', 'sub_type' => 'non_current_liability', 'normal_balance' => 'credit', 'description' => 'Long-term corporate credit obligations'],

            // EQUITY (3000 - 3999)
            ['code' => '3010', 'name' => 'Common Capital Stock', 'type' => 'equity', 'sub_type' => 'equity', 'normal_balance' => 'credit', 'description' => 'Founders and investor paid-in capital'],
            ['code' => '3020', 'name' => 'Additional Paid-in Capital', 'type' => 'equity', 'sub_type' => 'equity', 'normal_balance' => 'credit', 'description' => 'Excess contributed capital'],
            ['code' => '3990', 'name' => 'Retained Earnings', 'type' => 'equity', 'sub_type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true, 'description' => 'Cumulative net earnings retained in business'],

            // REVENUE (4000 - 4999)
            ['code' => '4010', 'name' => 'Software Engineering & Consulting', 'type' => 'revenue', 'sub_type' => 'operating_revenue', 'normal_balance' => 'credit', 'description' => 'Professional advisory and software development fees'],
            ['code' => '4020', 'name' => 'Recurring SaaS Platform Subscriptions', 'type' => 'revenue', 'sub_type' => 'operating_revenue', 'normal_balance' => 'credit', 'description' => 'Monthly and annual cloud platform licenses'],
            ['code' => '4030', 'name' => 'Hardware & Server Infrastructure Sales', 'type' => 'revenue', 'sub_type' => 'operating_revenue', 'normal_balance' => 'credit', 'description' => 'Procurement and delivery of turnkey systems'],
            ['code' => '4900', 'name' => 'Interest & Incidental Income', 'type' => 'revenue', 'sub_type' => 'other_revenue', 'normal_balance' => 'credit', 'description' => 'Bank interest and treasury yields'],

            // EXPENSES (5000 - 6999)
            ['code' => '5010', 'name' => 'COGS - Hardware Procurement', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold', 'normal_balance' => 'debit', 'description' => 'Direct cost of hardware sold to clients'],
            ['code' => '5020', 'name' => 'COGS - Cloud Delivery Infrastructure', 'type' => 'expense', 'sub_type' => 'cost_of_goods_sold', 'normal_balance' => 'debit', 'description' => 'Direct hosting cost required to serve client workloads'],
            ['code' => '6010', 'name' => 'Office Facility & Lease Expense', 'type' => 'expense', 'sub_type' => 'operating_expense', 'normal_balance' => 'debit', 'description' => 'Monthly facilities rent and utilities'],
            ['code' => '6020', 'name' => 'Cloud Servers & Datacenter Subscriptions', 'type' => 'expense', 'sub_type' => 'operating_expense', 'normal_balance' => 'debit', 'description' => 'Internal infrastructure subscriptions'],
            ['code' => '6030', 'name' => 'Staff Salaries & Benefits', 'type' => 'expense', 'sub_type' => 'operating_expense', 'normal_balance' => 'debit', 'description' => 'Regular payroll compensations'],
            ['code' => '6040', 'name' => 'Legal, Audit & Professional Advisory', 'type' => 'expense', 'sub_type' => 'operating_expense', 'normal_balance' => 'debit', 'description' => 'External CPA and compliance fees'],
            ['code' => '6050', 'name' => 'Sales, Marketing & PR Campaigns', 'type' => 'expense', 'sub_type' => 'operating_expense', 'normal_balance' => 'debit', 'description' => 'Client acquisition campaigns and promotions'],
            ['code' => '6060', 'name' => 'Depreciation Expense', 'type' => 'expense', 'sub_type' => 'operating_expense', 'normal_balance' => 'debit', 'description' => 'Periodic depreciation on fixed assets'],
        ];

        foreach ($coaList as $acc) {
            Account::firstOrCreate(['code' => $acc['code']], $acc);
        }

        // 4. Seed Contacts (Customers & Vendors)
        $cust1 = Contact::firstOrCreate(
            ['name' => 'Acme Global Technologies Inc.'],
            [
                'type' => 'customer',
                'company_name' => 'Acme Holdings Corp',
                'email' => 'finance@acmeglobal.com',
                'phone' => '+1 (555) 234-5678',
                'tax_number' => 'US-EIN-9922114',
                'address' => '742 Evergreen Terrace, Silicon Valley, CA 94025',
                'credit_limit' => 50000.00,
            ]
        );

        $cust2 = Contact::firstOrCreate(
            ['name' => 'Nexus Systems International'],
            [
                'type' => 'customer',
                'company_name' => 'Nexus Group Ltd',
                'email' => 'accounts@nexussystems.io',
                'phone' => '+1 (555) 876-5432',
                'tax_number' => 'US-EIN-5544332',
                'address' => '100 Innovation Boulevard, Austin, TX 78701',
                'credit_limit' => 30000.00,
            ]
        );

        $vend1 = Contact::firstOrCreate(
            ['name' => 'Prime Cloud Infrastructure Corp'],
            [
                'type' => 'vendor',
                'company_name' => 'Prime Cloud Global LLC',
                'email' => 'billing@primecloud.net',
                'phone' => '+1 (555) 345-6789',
                'tax_number' => 'US-EIN-1122334',
                'address' => '900 Hyper-Scale Parkway, Seattle, WA 98101',
                'credit_limit' => 20000.00,
            ]
        );

        $vend2 = Contact::firstOrCreate(
            ['name' => 'Metro Office Real Estate LLC'],
            [
                'type' => 'vendor',
                'company_name' => 'Metro Property Management',
                'email' => 'leases@metroprop.com',
                'phone' => '+1 (555) 456-7890',
                'tax_number' => 'US-EIN-7788990',
                'address' => '1 Wall Street Plaza, New York, NY 10005',
                'credit_limit' => 15000.00,
            ]
        );

        // 5. Seed Products & Services
        $revConsult = Account::where('code', '4010')->first();
        $revSaaS = Account::where('code', '4020')->first();
        $revHw = Account::where('code', '4030')->first();

        $cogsDelivery = Account::where('code', '5020')->first();
        $cogsHw = Account::where('code', '5010')->first();
        $expCloud = Account::where('code', '6020')->first();

        $prodConsult = Product::firstOrCreate(
            ['code' => 'SRV-ARCH-CONSULT'],
            [
                'name' => 'Senior Architecture Advisory (Hourly)',
                'type' => 'service',
                'income_account_id' => $revConsult->id,
                'expense_account_id' => $cogsDelivery->id,
                'sale_price' => 175.00,
                'purchase_price' => 0.00,
                'tax_rate' => 8.25,
                'description' => 'High-level cloud and financial architecture consulting',
            ]
        );

        $prodSaaS = Product::firstOrCreate(
            ['code' => 'LIC-ENTERPRISE-MO'],
            [
                'name' => 'ApexAIS Enterprise Platform License (Monthly)',
                'type' => 'service',
                'income_account_id' => $revSaaS->id,
                'expense_account_id' => $expCloud->id,
                'sale_price' => 1250.00,
                'purchase_price' => 0.00,
                'tax_rate' => 0.00,
                'description' => 'Monthly dedicated enterprise accounting instance subscription',
            ]
        );

        $prodServer = Product::firstOrCreate(
            ['code' => 'HW-SRV-2U-CLUSTER'],
            [
                'name' => 'Turnkey Database Server Appliance',
                'type' => 'goods',
                'income_account_id' => $revHw->id,
                'expense_account_id' => $cogsHw->id,
                'sale_price' => 4500.00,
                'purchase_price' => 2800.00,
                'tax_rate' => 8.25,
                'quantity_on_hand' => 12.00,
                'description' => 'Pre-configured PostgreSQL 18 enterprise hardware appliance',
            ]
        );

        // 6. Post Initial Opening Balanced Journal Entries
        $bankAccount = Account::where('code', '1020')->first();
        $capitalAccount = Account::where('code', '3010')->first();
        $rentAccount = Account::where('code', '6010')->first();

        // Check if initial opening entry already exists
        if (! JournalEntry::where('reference', 'FOUNDER-CAPITAL')->exists()) {
            $accountingService->createJournalEntry([
                'entry_number' => 'JE-INIT-0001',
                'date' => Carbon::createFromDate($currentYear, 1, 2)->toDateString(),
                'reference' => 'FOUNDER-CAPITAL',
                'description' => 'Initial Equity Capital Infusion by Founding Partners',
                'source_type' => 'manual',
                'items' => [
                    [
                        'account_id' => $bankAccount->id,
                        'type' => 'debit',
                        'amount' => 150000.00,
                        'memo' => 'Corporate bank operating deposit',
                    ],
                    [
                        'account_id' => $capitalAccount->id,
                        'type' => 'credit',
                        'amount' => 150000.00,
                        'memo' => 'Authorized common capital stock',
                    ],
                ],
            ], $admin, true);

            // Rent Payment Entry
            $accountingService->createJournalEntry([
                'entry_number' => 'JE-RENT-0001',
                'date' => Carbon::createFromDate($currentYear, 1, 5)->toDateString(),
                'reference' => 'CHK-1002',
                'description' => 'Headquarters Facility Lease Advance Payment',
                'source_type' => 'manual',
                'items' => [
                    [
                        'account_id' => $rentAccount->id,
                        'type' => 'debit',
                        'amount' => 6500.00,
                        'memo' => 'Facilities lease payment',
                    ],
                    [
                        'account_id' => $bankAccount->id,
                        'type' => 'credit',
                        'amount' => 6500.00,
                        'memo' => 'Wire transfer payout',
                    ],
                ],
            ], $accountant, true);
        }

        // 7. Seed Initial Sales Invoice and Payment
        if (! Invoice::where('invoice_number', 'INV-2026-0001')->exists()) {
            $qty = 40;
            $price = 175.00;
            $subtotal = $qty * $price; // 7,000
            $tax = round($subtotal * 0.0825, 4); // 577.50
            $total = $subtotal + $tax; // 7,577.50

            $inv = Invoice::create([
                'invoice_number' => 'INV-2026-0001',
                'customer_id' => $cust1->id,
                'issue_date' => Carbon::now()->subDays(15)->toDateString(),
                'due_date' => Carbon::now()->addDays(15)->toDateString(),
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'discount_total' => 0,
                'total_amount' => $total,
                'paid_amount' => 0,
                'status' => 'draft',
                'notes' => 'Net 30 payment terms. Wire routing on receipt.',
                'created_by' => $accountant->id,
            ]);

            $inv->items()->create([
                'product_id' => $prodConsult->id,
                'account_id' => $revConsult->id,
                'description' => '40 Hours Enterprise Security Advisory Consulting',
                'quantity' => $qty,
                'unit_price' => $price,
                'tax_percent' => 8.25,
                'discount_percent' => 0,
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $total,
            ]);

            // Post Invoice to GL
            $accountingService->postInvoice($inv, $accountant);

            // Record Partial Payment
            $partialPay = 5000.00;
            $pay = Payment::create([
                'payment_number' => 'REC-2026-0001',
                'type' => 'received',
                'contact_id' => $cust1->id,
                'invoice_id' => $inv->id,
                'bank_account_id' => $bankAccount->id,
                'payment_date' => Carbon::now()->subDays(5)->toDateString(),
                'amount' => $partialPay,
                'payment_method' => 'Bank Transfer',
                'reference' => 'WIRE-ACME-8891',
                'notes' => 'Partial installment received via wire transfer.',
                'created_by' => $accountant->id,
            ]);

            $accountingService->postInvoicePayment($pay, $accountant);
        }

        // 8. Seed Initial Vendor Bill and Payment
        if (! Bill::where('bill_number', 'BILL-2026-0001')->exists()) {
            $billTotal = 3200.00;
            $bill = Bill::create([
                'bill_number' => 'BILL-2026-0001',
                'vendor_id' => $vend1->id,
                'bill_date' => Carbon::now()->subDays(20)->toDateString(),
                'due_date' => Carbon::now()->addDays(10)->toDateString(),
                'subtotal' => $billTotal,
                'tax_total' => 0,
                'total_amount' => $billTotal,
                'paid_amount' => 0,
                'status' => 'draft',
                'notes' => 'Dedicated cloud clusters monthly compute consumption.',
                'created_by' => $accountant->id,
            ]);

            $bill->items()->create([
                'product_id' => null,
                'account_id' => $expCloud->id,
                'description' => 'Enterprise Cloud Kubernetes & Storage Compute Clusters',
                'quantity' => 1,
                'unit_price' => $billTotal,
                'tax_percent' => 0,
                'subtotal' => $billTotal,
                'tax_amount' => 0,
                'total' => $billTotal,
            ]);

            $accountingService->postBill($bill, $accountant);

            // Record Full Payment for Bill
            $billPay = Payment::create([
                'payment_number' => 'DIS-2026-0001',
                'type' => 'sent',
                'contact_id' => $vend1->id,
                'bill_id' => $bill->id,
                'bank_account_id' => $bankAccount->id,
                'payment_date' => Carbon::now()->subDays(3)->toDateString(),
                'amount' => $billTotal,
                'payment_method' => 'Bank Transfer',
                'reference' => 'ACH-PRIME-0012',
                'notes' => 'Automatic ACH disbursement.',
                'created_by' => $accountant->id,
            ]);

            $accountingService->postBillPayment($billPay, $accountant);
        }
    }
}
