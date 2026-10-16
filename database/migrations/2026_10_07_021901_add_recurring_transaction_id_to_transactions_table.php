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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')
                ->nullable()
                ->after('user_id')
                ->constrained('recurring_transactions')
                ->nullOnDelete();

            // The date the occurrence was scheduled for, which is not
            // necessarily the date it was posted: a missed run is caught up
            // later, and the transaction belongs to the original day.
            $table->date('occurrence_on')->nullable()->after('occurred_at');
        });

        // A template may produce an occurrence exactly once, however many
        // times the scheduler runs. Enforced here rather than in application
        // code because two concurrent runs would both pass an existence check.
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(
                ['recurring_transaction_id', 'occurrence_on'],
                'transactions_recurring_occurrence_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_recurring_occurrence_unique');
            $table->dropConstrainedForeignId('recurring_transaction_id');
            $table->dropColumn('occurrence_on');
        });
    }
};
