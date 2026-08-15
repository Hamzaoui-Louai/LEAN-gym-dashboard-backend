<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes the date/status columns the finance and dashboard aggregates
     * filter on (every monthlyOverview windowed query and the daily
     * check-in lookups previously did full table scans).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'paid_at']);
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->index('starts_at');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->index('paid_at');
        });

        Schema::table('repair_bills', function (Blueprint $table) {
            $table->index('repair_date');
        });

        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->index('purchase_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_bills', function (Blueprint $table) {
            $table->dropIndex(['purchase_date']);
        });

        Schema::table('repair_bills', function (Blueprint $table) {
            $table->dropIndex(['repair_date']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['starts_at']);
        });

        Schema::table('checkins', function (Blueprint $table) {
            $table->dropIndex(['date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
        });
    }
};
