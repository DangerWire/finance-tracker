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
        Schema::table('budgets', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('period_month');
            $table->index(['user_id', 'is_recurring', 'period_month'], 'budgets_user_recurring_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_user_recurring_period_index');
            $table->dropColumn('is_recurring');
        });
    }
};
