<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number', 20)->nullable()->after('status');
            $table->boolean('whatsapp_notifications_enabled')->default(false)->after('whatsapp_number');
            $table->timestamp('whatsapp_consent_at')->nullable()->after('whatsapp_notifications_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_number',
                'whatsapp_notifications_enabled',
                'whatsapp_consent_at',
            ]);
        });
    }
};
