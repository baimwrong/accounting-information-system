<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Chart of Accounts
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/{account}/statement', [AccountController::class, 'statement'])->name('accounts.statement');
    Route::middleware('role:admin|accountant')->group(function () {
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    });
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('accounts.destroy');

    // Journal Entries
    Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index');
    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])->name('journal-entries.create');
        Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
        Route::post('/journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
        Route::post('/journal-entries/{journalEntry}/void', [JournalEntryController::class, 'void'])->name('journal-entries.void');
    });
    Route::get('/journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->name('journal-entries.show');

    // Contacts (Customers & Vendors)
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('/contacts/create', [ContactController::class, 'create'])->name('contacts.create');
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::match(['put', 'patch'], '/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    });
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('contacts.destroy');

    // Products / Inventory / Services
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::middleware('role:admin|accountant')->group(function () {
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->name('products.update');
    });
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('products.destroy');

    // Invoices (Sales / AR)
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
    });
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    // Bills (Purchases / AP)
    Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
    Route::middleware('role:admin|accountant')->group(function () {
        Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
        Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
        Route::post('/bills/{bill}/payments', [BillController::class, 'recordPayment'])->name('bills.payments.store');
    });
    Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');

    // Payments & Treasury
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::middleware('role:admin|accountant')->group(function () {
        Route::post('/payments/transfer', [PaymentController::class, 'transfer'])->name('payments.transfer');
    });
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    // Financial Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/income-statement', [ReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('/general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/ar-aging', [ReportController::class, 'arAging'])->name('ar-aging');
        Route::get('/ap-aging', [ReportController::class, 'apAging'])->name('ap-aging');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/export/{type}', [ReportController::class, 'export'])->name('export');
    });

    // Fiscal Years
    Route::get('/fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
    Route::middleware('role:admin')->group(function () {
        Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::post('/fiscal-years/{fiscalYear}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
    });

    // Bulk Import (Admin Only)
    Route::middleware('role:admin')->prefix('imports')->name('imports.')->group(function () {
        Route::get('/', [BulkImportController::class, 'index'])->name('index');
        Route::post('/accounts', [BulkImportController::class, 'importAccounts'])->name('accounts');
        Route::post('/contacts', [BulkImportController::class, 'importContacts'])->name('contacts');
        Route::post('/products', [BulkImportController::class, 'importProducts'])->name('products');
        Route::post('/journal-entries', [BulkImportController::class, 'importJournalEntries'])->name('journal-entries');
        Route::get('/template/{type}', [BulkImportController::class, 'downloadTemplate'])->name('template');
        Route::get('/batches/{batch}', [BulkImportController::class, 'showBatch'])->name('batch.show');
    });

    // Audit Trail
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:admin|auditor')
        ->name('audit-logs.index');
});
