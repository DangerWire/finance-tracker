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
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->unique()->after('currency');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_username');
            $table->timestamp('telegram_link_token_expires_at')->nullable()->after('telegram_link_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_username',
                'telegram_link_token',
                'telegram_link_token_expires_at',
            ]);
        });
    }
};
