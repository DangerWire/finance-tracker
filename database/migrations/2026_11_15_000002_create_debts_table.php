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
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('note', 255)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('pending_acceptance');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('lender_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('borrower_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['lender_id', 'status'], 'idx_debts_lender');
            $table->index(['borrower_id', 'status'], 'idx_debts_borrower');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
