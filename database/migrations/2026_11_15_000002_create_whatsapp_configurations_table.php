<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('meta_cloud');
            $table->text('endpoint');
            $table->text('token')->nullable();
            $table->string('phone_number_id', 100)->nullable();
            $table->string('sender_number', 30)->nullable();
            $table->boolean('is_active')->default(true);

            // Optional fields for custom HTTP providers.
            $table->string('custom_method', 10)->default('POST');
            $table->string('custom_auth_header', 100)->default('Authorization');
            $table->string('custom_auth_scheme', 50)->default('Bearer');
            $table->text('custom_payload_template')->nullable();

            // Allows a safe, provider-specific health check when available.
            $table->string('test_method', 10)->default('GET');
            $table->text('test_endpoint')->nullable();

            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->text('last_test_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_configurations');
    }
};
