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
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('statement_date')->index();
            $table->decimal('statement_balance', 15, 4);
            $table->decimal('reconciled_balance', 15, 4)->default(0);
            $table->decimal('difference', 15, 4)->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft, completed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_reconciliation_id')->nullable()->constrained('bank_reconciliations')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_reconciliation_id');
            $table->dropColumn('reconciled_at');
        });

        Schema::dropIfExists('bank_reconciliations');
    }
};
