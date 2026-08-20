<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->timestamp('last_freezed_at')->nullable()->after('ends_at');
            $table->timestamp('last_unfreezed_at')->nullable()->after('last_freezed_at');
            $table->timestamp('original_ends_at')->nullable()->after('last_unfreezed_at');
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['last_freezed_at', 'last_unfreezed_at', 'original_ends_at']);
        });
    }
};
