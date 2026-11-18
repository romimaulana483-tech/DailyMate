<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_settings', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_number', 20)->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('daily_enabled')->default(false);
            $table->boolean('weekly_enabled')->default(false);
            $table->boolean('monthly_enabled')->default(false);
            $table->string('send_time', 5)->default('08:00');
            $table->date('last_daily_sent_on')->nullable();
            $table->date('last_weekly_sent_on')->nullable();
            $table->date('last_monthly_sent_on')->nullable();
            $table->timestamps();
        });

        Schema::create('report_logs', function (Blueprint $table) {
            $table->id();
            $table->string('frequency', 20);
            $table->string('recipient_number', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('provider_message_id', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['frequency', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_logs');
        Schema::dropIfExists('report_settings');
    }
};
