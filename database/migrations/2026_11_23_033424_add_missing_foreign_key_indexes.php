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
            $table->index('import_id', 'transactions_import_id_index');
        });

        Schema::table('transaction_splits', function (Blueprint $table) {
            $table->index('transaction_id', 'transaction_splits_transaction_id_index');
        });

        Schema::table('transaction_imports', function (Blueprint $table) {
            $table->index('user_id', 'transaction_imports_user_id_index');
        });

        Schema::table('friendships', function (Blueprint $table) {
            $table->index('friend_id', 'friendships_friend_id_index');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->index('creator_id', 'debts_creator_id_index');
            $table->index('lender_transaction_id', 'debts_lender_transaction_id_index');
            $table->index('borrower_transaction_id', 'debts_borrower_transaction_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropIndex('debts_creator_id_index');
            $table->dropIndex('debts_lender_transaction_id_index');
            $table->dropIndex('debts_borrower_transaction_id_index');
        });

        Schema::table('friendships', function (Blueprint $table) {
            $table->dropIndex('friendships_friend_id_index');
        });

        Schema::table('transaction_imports', function (Blueprint $table) {
            $table->dropIndex('transaction_imports_user_id_index');
        });

        Schema::table('transaction_splits', function (Blueprint $table) {
            $table->dropIndex('transaction_splits_transaction_id_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_import_id_index');
        });
    }
};
