<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_entry_items', function (Blueprint $table) {
            $table->index(['account_id', 'type'], 'idx_je_items_account_type');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['status', 'date'], 'idx_journal_entries_status_date');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'idx_invoices_customer_status');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->index(['vendor_id', 'status'], 'idx_bills_vendor_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entry_items', function (Blueprint $table) {
            $table->dropIndex('idx_je_items_account_type');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_journal_entries_status_date');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_customer_status');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex('idx_bills_vendor_status');
        });
    }
};
