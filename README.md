# Accounting Information System (AIS) - Production-Ready Web Application

[![Laravel 13](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php)](https://php.net)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-336791?style=flat&logo=postgresql)](https://www.postgresql.org)
[![Bootstrap 5.3.3](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?style=flat&logo=bootstrap)](https://getbootstrap.com)
[![Air--Gapped / Offline Ready](https://img.shields.io/badge/Intranet-100%25_Air--Gapped_Ready-28a745?style=flat)](#offline--air-gapped-intranet-architecture)
[![PHPUnit Passed](https://img.shields.io/badge/Tests-Passing_10%2F10-brightgreen)](#automated-testing--code-quality)

**Accounting Information System (AIS)** is a production-grade, double-entry web application built with **Laravel 13**, architected for **PostgreSQL 18**, and styled with **Bootstrap 5.3.3**, **FontAwesome 6.7.2**, and **Bootstrap Icons 1.11.3**. 

The system is designed specifically for enterprise and internal network environments. **All CSS, JavaScript, and font libraries are stored locally in the project repository (`public/vendor/`)**, ensuring 100% functionality in air-gapped corporate intranets without public internet or external CDN access.

---

## Table of Contents
1. [Core Features & Architecture](#core-features--architecture)
2. [Offline / Air-Gapped Intranet Architecture](#offline--air-gapped-intranet-architecture)
3. [Technology Stack & Requirements](#technology-stack--requirements)
4. [Step-by-Step Installation & Setup](#step-by-step-installation--setup)
   - [Configuring PostgreSQL 18 with Laragon](#configuring-postgresql-18-with-laragon)
   - [Database Migrations & Seeding](#database-migrations--seeding)
   - [Instant SQLite Alternative for Local Preview](#instant-sqlite-alternative-for-local-preview)
5. [Default System Credentials & Roles](#default-system-credentials--roles)
6. [Admin Bulk Data Import Portal](#admin-bulk-data-import-portal)
   - [Chart of Accounts CSV Specification](#1-chart-of-accounts-csv-specification)
   - [Contacts (Customers & Vendors) CSV Specification](#2-contacts-customers--vendors-csv-specification)
   - [Products & Services CSV Specification](#3-products--services-csv-specification)
   - [Multi-line Journal Entries CSV Specification](#4-multi-line-journal-entries-csv-specification)
7. [Automated Testing & Code Quality](#automated-testing--code-quality)
8. [Directory & System Architecture](#directory--system-architecture)

---

## Core Features & Architecture

### 1. Robust Double-Entry General Ledger (GL) Engine
- **Mathematical Parity Verification**: Every journal entry strictly enforces the fundamental accounting equation $\sum \text{Debit} \equiv \sum \text{Credit}$ before persisting to the ledger.
- **Sequential Document Numbering**: Automatically generates standardized document identifiers (`JE-YYYY-XXXX`, `INV-YYYY-XXXX`, `BILL-YYYY-XXXX`, `PAY-YYYY-XXXX`).
- **Atomic Balance Updates**: Account balances are updated atomically inside database transactions.
- **Period Locks & Fiscal Year Integrity**: Validates transactions against active fiscal years. Closed periods prevent retroactive modifications.
- **Audited Journal Reversals & Voiding**: Reversing entries maintain an immutable, tamper-proof audit trail rather than silently deleting posted records.

### 2. Comprehensive Chart of Accounts (COA)
- 5 Standard Account Classifications: **Assets**, **Liabilities**, **Equity**, **Revenue**, and **Expenses**.
- Specialized Sub-types: Current Assets, Non-Current Assets, Current Liabilities, Long-Term Debt, Capital, Retained Earnings, Cost of Goods Sold (COGS), Operating Expenses, Non-Operating Revenue/Expense.
- Hierarchical Parent-Child structures.
- Normal Balance direction tracking (`debit` or `credit`) with automated balance calculation formulas.
- Detailed General Ledger account statements with running balance calculations.

### 3. Accounts Receivable (AR) & Sales Invoicing
- Complete sales cycle: Draft $\rightarrow$ Issued $\rightarrow$ Partially Paid $\rightarrow$ Paid / Overdue / Cancelled.
- Line items with product selection, unit pricing, quantities, and automated tax calculations.
- Automatic GL posting upon issuance:
  - **Debit**: Accounts Receivable (Current Asset)
  - **Credit**: Sales Revenue (Revenue)
  - **Credit**: Sales Tax Payable (Current Liability, if applicable)
- In-line customer payment processing with automatic balance adjustments.

### 4. Accounts Payable (AP) & Vendor Billing
- Vendor purchasing workflow: Draft $\rightarrow$ Received $\rightarrow$ Partially Paid $\rightarrow$ Paid / Overdue / Cancelled.
- Itemized billing linked to inventory or operating expense accounts.
- Automatic GL posting upon receipt:
  - **Debit**: Inventory / Operating Expense
  - **Debit**: Purchase Tax Paid (Input Tax Asset)
  - **Credit**: Accounts Payable (Current Liability)
- Vendor disbursements and payment recording with automated bill reconciliation.

### 5. Treasury & Cash Management
- Payment tracking for both Receipts (Customer Inflows) and Disbursements (Vendor Outflows).
- Direct linkage to Bank/Cash accounts with instant GL updates.

### 6. Real-Time Financial Statements & Reports
- **Trial Balance**: Validates ledger equilibrium by listing all active debit and credit balances with net verification.
- **Balance Sheet**: Instant snapshot of financial health proving $\text{Assets} = \text{Liabilities} + \text{Equity}$ (with dynamic Net Income accumulation from the current period).
- **Income Statement (Profit & Loss)**: Segregated into Operating Revenue, Cost of Goods Sold (COGS), Gross Profit, Operating Expenses, and Net Profit/Loss.
- **General Ledger Account Statement**: Detailed journal line inspection with date filtering, running balances, and direct drill-down links.

### 7. Multi-Tier Role-Based Access Control (RBAC)
- **Admin**: Full control including user administration, fiscal year period locks, audit logs, and Bulk Data Import.
- **Accountant**: Operational accounting access: Journal entries, Invoices, Bills, Payments, COA, and Financial Reports.
- **Auditor**: Strict Read-Only compliance access: inspection of Financial Statements, General Ledger records, and immutable system audit logs.

### 8. Immutable System Audit Trail
- Automated logging of critical accounting actions: journal posting, invoice issuance, bill processing, payments, fiscal year closure, and bulk data imports.
- Records timestamp, user identity, IP address, user agent, action type, and related model targets.

---

## Offline / Air-Gapped Intranet Architecture

In many financial, governmental, and corporate deployments, systems must operate within secure internal networks isolated from the public internet.

All external assets have been installed and statically bundled inside `public/vendor/`:

```
public/
└── vendor/
    ├── bootstrap/
    │   ├── css/
    │   │   ├── bootstrap.min.css
    │   │   └── bootstrap.min.css.map
    │   └── js/
    │       ├── bootstrap.bundle.min.js
    │       └── bootstrap.bundle.min.js.map
    ├── bootstrap-icons/
    │   ├── bootstrap-icons.css
    │   └── fonts/
    │       ├── bootstrap-icons.woff
    │       └── bootstrap-icons.woff2
    └── fontawesome/
        ├── css/
        │   └── all.min.css
        └── webfonts/
            ├── fa-solid-900.woff2
            ├── fa-regular-400.woff2
            ├── fa-brands-400.woff2
            └── ... (all font files included)
```

Both Blade layout files (`resources/views/layouts/app.blade.php` and `resources/views/layouts/guest.blade.php`) load assets exclusively via the local `asset('vendor/...')` helper:
```html
<!-- Fully offline local stylesheet dependencies -->
<link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

<!-- Fully offline local script dependencies -->
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
```

Zero external HTTP/HTTPS requests (such as Google Fonts or unpkg/cdnjs CDNs) are made. Dashboard trend visualizations are generated using pure inline vector SVGs without external charting dependencies.

---

## Technology Stack & Requirements

- **PHP Version**: `^8.4` (tested and verified with PHP 8.4.12)
- **Framework**: `Laravel 13.x` (13.35.0)
- **Target Database**: `PostgreSQL 18` (or 16 / 17) via `pdo_pgsql`
- **Fallback / Testing Database**: `SQLite 3` via `pdo_sqlite`
- **Frontend**: Bootstrap 5.3.3, Bootstrap Icons 1.11.3, FontAwesome 6.7.2
- **PHP Extensions Required**:
  - `pdo_pgsql` & `pgsql`
  - `pdo_sqlite` (for in-memory test suite)
  - `mbstring`, `openssl`, `bcmath`, `curl`, `fileinfo`, `ctype`, `xml`

---

## Step-by-Step Installation & Setup

### Configuring PostgreSQL 18 with Laragon

If you are using Laragon on Windows or a standard Linux/macOS environment:

#### 1. Enable PostgreSQL Extensions in PHP
Ensure that the `pdo_pgsql` and `pgsql` extensions are enabled in your active PHP installation (`php.ini`).
In Laragon:
1. Click **Menu** $\rightarrow$ **PHP** $\rightarrow$ **Quick Settings** $\rightarrow$ enable `pdo_pgsql` and `pgsql`.
2. Or open `php.ini` directly and uncomment:
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```

#### 2. Install and Start PostgreSQL 18
If you have PostgreSQL 18 installed (via Laragon PostgreSQL add-on or the official EDB installer):
1. Start the PostgreSQL service on port `5432`.
2. Connect using `psql` or pgAdmin and create the application database:
   ```sql
   CREATE DATABASE accounting_system;
   ```

#### 3. Configure `.env`
Verify that your `.env` contains the PostgreSQL credentials:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=accounting_system
DB_USERNAME=postgres
DB_PASSWORD=postgres
DB_SSLMODE=prefer
```

---

### Database Migrations & Seeding

Run the database migrations and seeders using the Laravel Artisan CLI (ensure your PHP 8.4 binary is in your PATH):

```bash
# Run all 12 migrations and populate demo Chart of Accounts, contacts, products, and double-entry journals:
php artisan migrate:fresh --seed
```

---

### Instant SQLite Alternative for Local Preview

If PostgreSQL 18 is **not yet installed** on your workstation and you wish to run and test the application immediately using Laragon's built-in SQLite:

1. Update `.env` to point to SQLite:
   ```env
   DB_CONNECTION=sqlite
   DB_DATABASE=database/database.sqlite
   ```
2. Run migrations and seeders:
   ```bash
   php artisan migrate:fresh --seed
   ```
3. Start the built-in development server:
   ```bash
   php artisan serve
   ```
4. Access the web application at `http://127.0.0.1:8000`.

---

## Default System Credentials & Roles

When you run `php artisan db:seed`, the system automatically provisions 3 enterprise users, an active Fiscal Year (`FY 2026`), 27 foundational Chart of Accounts, sample contacts, products, and balanced transactions:

| Role | Email | Password | Scope & Privileges |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin@example.com` | `password` | Full administrative control, Bulk Import Portal, Fiscal Year Locks, System Audit Logs |
| **Senior Accountant** | `accountant@example.com` | `password` | Chart of Accounts, Journal Entries, Invoicing, Vendor Bills, Payments, Financial Statements |
| **Compliance Auditor** | `auditor@example.com` | `password` | Read-only inspection of Financial Reports, General Ledger, Audit Logs |

---

## Admin Bulk Data Import Portal

Accessible strictly to users with the `admin` role at `/imports` (Sidebar $\rightarrow$ **Bulk Import Data**). 

The module supports atomic CSV ingestion across 4 core operational domains, complete with downloadable CSV templates, row-level error reporting, and audit trail integration.

```
Admin Portal: /imports
├── Download Template (/imports/template/{type})
├── Import Chart of Accounts (/imports/accounts)
├── Import Contacts (/imports/contacts)
├── Import Products (/imports/products)
└── Import Multi-line Journal Entries (/imports/journal-entries)
```

### 1. Chart of Accounts CSV Specification

Uploads or updates general ledger accounts.

#### Columns
- `code` *(Required)*: Unique alphanumeric account code (e.g., `1010`, `4010`).
- `name` *(Required)*: Official title of the account.
- `type` *(Required)*: Must be one of `asset`, `liability`, `equity`, `revenue`, or `expense`.
- `sub_type` *(Optional)*: Sub-classification (e.g., `current_asset`, `operating_revenue`, `cost_of_goods_sold`).
- `normal_balance` *(Optional)*: `debit` or `credit` (defaults automatically based on account type).
- `parent_code` *(Optional)*: Parent account code for hierarchy.
- `description` *(Optional)*: Account notes.

#### Sample CSV Content
```csv
code,name,type,sub_type,normal_balance,parent_code,description
1010,Petty Cash,asset,current_asset,debit,,Daily operational cash
1020,Operating Bank Account,asset,current_asset,debit,,Main checking account
1200,Accounts Receivable,asset,current_asset,debit,,Amounts owed by customers
2010,Accounts Payable,liability,current_liability,credit,,Amounts owed to suppliers
3010,Common Capital Stock,equity,equity,credit,,Initial paid-up capital
4010,Consulting Revenue,revenue,operating_revenue,credit,,Core revenue
5010,Cost of Goods Sold,expense,cost_of_goods_sold,debit,,Direct costs
6010,Office Rent Expense,expense,operating_expense,debit,,Monthly lease
```

---

### 2. Contacts (Customers & Vendors) CSV Specification

Imports customers and suppliers.

#### Columns
- `type` *(Required)*: Must be `customer`, `vendor`, or `both`.
- `name` *(Required)*: Full individual or organization name.
- `company_name` *(Optional)*: Registered company trade name.
- `email` *(Optional)*: Primary billing email address.
- `phone` *(Optional)*: Primary phone number.
- `tax_number` *(Optional)*: VAT / Tax Identification Number (TIN).
- `address` *(Optional)*: Physical billing or shipping address.
- `credit_limit` *(Optional)*: Allowed credit ceiling (numeric).
- `opening_balance` *(Optional)*: Historical opening balance (numeric).

#### Sample CSV Content
```csv
type,name,company_name,email,phone,tax_number,address,credit_limit,opening_balance
customer,Acme Corporation,Acme Global Ltd,billing@acme.com,+1-555-0100,TAX-998811,100 Silicon Way Tech City,25000.00,0.00
customer,Nexus Enterprises,Nexus Group,ap@nexus.io,+1-555-0144,TAX-334455,45 Innovation Blvd,15000.00,0.00
vendor,Cloud Infra Providers,Cloud Provider Inc,support@cloudinfra.com,+1-555-0199,TAX-772211,700 Data Center Dr,50000.00,0.00
vendor,Prime Office Supplies,Prime Retail LLC,sales@primeoffice.com,+1-555-0182,TAX-661122,12 Market St,10000.00,0.00
```

---

### 3. Products & Services CSV Specification

Imports items for invoicing and purchasing, mapped to GL revenue and expense accounts.

#### Columns
- `code` *(Required)*: Unique SKU or product identifier.
- `name` *(Required)*: Item description or title.
- `type` *(Optional)*: `goods` or `service` (defaults to `goods`).
- `sale_price` *(Optional)*: Default customer selling price.
- `purchase_price` *(Optional)*: Default supplier cost price.
- `tax_rate` *(Optional)*: Default sales tax percentage (e.g. `10.00` for 10%).
- `income_account_code` *(Required)*: Valid account code for sales revenue credit (e.g. `4010`).
- `expense_account_code` *(Required)*: Valid account code for cost/expense debit (e.g. `5010` or `6010`).
- `asset_account_code` *(Optional)*: Valid inventory asset account code (e.g. `1020` or `1400`).
- `quantity_on_hand` *(Optional)*: Current stock quantity.
- `description` *(Optional)*: Product details.

#### Sample CSV Content
```csv
code,name,type,sale_price,purchase_price,tax_rate,income_account_code,expense_account_code,asset_account_code,quantity_on_hand,description
SRV-CONSULT,Enterprise Consulting Hour,service,150.00,0.00,10.00,4010,6010,,0,Senior financial advisory
PRD-SERVER,Rackmount Server Hardware,goods,3200.00,2100.00,10.00,4010,5010,1020,15,Production 2U server
```

---

### 4. Multi-line Journal Entries CSV Specification

Imports balanced double-entry manual journal transactions. Lines sharing the same `entry_number` are grouped into a single atomic journal entry.

#### Validation Rules
1. Every entry group **must** mathematically balance: $\sum \text{Debit} == \sum \text{Credit}$.
2. Each line must contain either a debit or credit amount, never both.
3. Account codes must exist in the Chart of Accounts.

#### Columns
- `entry_number` *(Required)*: Grouping key (e.g., `JE-2026-0001`).
- `date` *(Required)*: Transaction date (`YYYY-MM-DD`).
- `reference` *(Optional)*: External reference or check number.
- `description` *(Optional)*: Header entry memo.
- `account_code` *(Required)*: GL account code to debit or credit.
- `debit` *(Required if not credit)*: Debit numeric amount (or `0.00`).
- `credit` *(Required if not debit)*: Credit numeric amount (or `0.00`).
- `memo` *(Optional)*: Line-item memo.

#### Sample CSV Content
```csv
entry_number,date,reference,description,account_code,debit,credit,memo
JE-2026-0001,2026-10-13,REF-STARTUP,Owner Capital Injection,1020,50000.00,0.00,Bank deposit
JE-2026-0001,2026-10-13,REF-STARTUP,Owner Capital Injection,3010,0.00,50000.00,Capital equity
JE-2026-0002,2026-10-13,CHK-1002,Office Rent Advance,6010,3500.00,0.00,Rent payment
JE-2026-0002,2026-10-13,CHK-1002,Office Rent Advance,1020,0.00,3500.00,Bank wire
```

---

## Automated Testing & Code Quality

The application includes an automated test suite verifying double-entry arithmetic, period locking, automated journal generation for AR/AP transactions, financial statements balance integrity, and bulk import CSV validation.

### Running PHPUnit Tests
Tests utilize an isolated in-memory SQLite database configured in `phpunit.xml`, allowing full execution without needing a running PostgreSQL instance:

```bash
# Run the test suite via Laravel Artisan:
php artisan test --compact
```

### Running Laravel Pint Code Formatter
All PHP code adheres to modern PSR-12 and Laravel Pint style standards:

```bash
# Run Laravel Pint formatting:
vendor/bin/pint --format agent
```

---

## Directory & System Architecture

```
accounting-information-system/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AccountController.php       # COA & GL Statements
│   │   │   ├── AuditLogController.php      # Immutable Audit Logs
│   │   │   ├── AuthController.php          # Session Authentication & Profile
│   │   │   ├── BillController.php          # Accounts Payable & Vendor Bills
│   │   │   ├── BulkImportController.php    # Admin CSV Bulk Ingestion Portal
│   │   │   ├── DashboardController.php     # Executive Metrics & SVG Trend Charts
│   │   │   ├── FiscalYearController.php    # Period Locks & Year-End Closures
│   │   │   ├── InvoiceController.php       # Accounts Receivable & Sales Invoicing
│   │   │   ├── JournalEntryController.php  # Manual Journals & Balancing UI
│   │   │   ├── PaymentController.php       # Treasury Cash Receipts & Disbursements
│   │   │   ├── ProductController.php       # Inventory & Service Catalog
│   │   │   └── ReportController.php        # Financial Statements (TB, BS, PL, GL)
│   │   └── Middleware/
│   │       └── CheckRole.php               # Multi-Role RBAC Middleware
│   ├── Models/
│   │   ├── Account.php                     # GL Account & Balance Logic
│   │   ├── AuditLog.php                    # System Audit Trail Entries
│   │   ├── Bill.php & BillItem.php         # Accounts Payable Entities
│   │   ├── Contact.php                     # Customers & Vendors
│   │   ├── FiscalYear.php                  # Fiscal Periods
│   │   ├── ImportBatch.php                 # Bulk Import Job Logs
│   │   ├── Invoice.php & InvoiceItem.php   # Accounts Receivable Entities
│   │   ├── JournalEntry.php & Item.php     # Double-Entry Core
│   │   ├── Payment.php                     # Cash Movement Records
│   │   ├── Product.php                     # Products & Services
│   │   └── User.php                        # Auth & Role Capabilities
│   └── Services/
│       ├── AccountingService.php           # Double-entry posting, balance mutations, auto GL
│       ├── BulkImportService.php           # Streaming CSV parsing, validation, error tracking
│       └── ReportService.php               # TB, Balance Sheet, Income Statement math
├── database/
│   ├── migrations/                         # 12 PostgreSQL 18-compatible migrations
│   └── seeders/
│       └── DatabaseSeeder.php              # Comprehensive accounting demo seed data
├── public/
│   └── vendor/                             # 100% Offline / Intranet Assets
│       ├── bootstrap/                      # Bootstrap 5.3.3 CSS & JS Bundle
│       ├── bootstrap-icons/                # Bootstrap Icons 1.11.3 & Webfonts
│       └── fontawesome/                    # FontAwesome 6.7.2 & Webfonts
├── resources/
│   └── views/
│       ├── accounts/                       # COA list, create modal, GL statement
│       ├── audit_logs/                     # Audit trail inspector
│       ├── auth/                           # Login & Profile views
│       ├── bills/                          # AP bills index, create, show
│       ├── contacts/                       # Customers & Vendors views
│       ├── dashboard/                      # Executive KPI dashboard
│       ├── fiscal_years/                   # Period locking manager
│       ├── imports/                        # Admin CSV Bulk Import Portal
│       ├── invoices/                       # AR sales invoices index, create, show
│       ├── journal_entries/                # Interactive dynamic journal balancer
│       ├── layouts/                        # app.blade.php & guest.blade.php (Offline)
│       ├── payments/                       # Treasury payment vouchers
│       ├── products/                       # Inventory catalog
│       └── reports/                        # Trial Balance, Balance Sheet, P&L, GL
└── tests/
    └── Feature/
        ├── AccountingTest.php              # Double-entry, period locks, auto-posting tests
        ├── BulkImportTest.php              # CSV template & bulk import validation tests
        └── ExampleTest.php                 # Authentication gateway test
```

---

## License

This application is distributed under the [MIT License](LICENSE).
