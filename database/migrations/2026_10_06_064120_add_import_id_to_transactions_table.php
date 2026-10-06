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
            // Groups every row produced by one upload. This is what makes a
            // re-import recoverable: the whole batch can be identified and
            // removed, instead of the user hunting for duplicates by hand.
            $table->foreignId('import_id')
                ->nullable()
                ->after('user_id')
                ->constrained('transaction_imports')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_id');
        });
    }
};
