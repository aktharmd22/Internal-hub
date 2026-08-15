<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();

            // Null applies the rule to every asset type.
            $table->string('asset_type', 20)->nullable();

            // Negative values fire after expiry: -3 means three days overdue.
            $table->integer('days_before');

            $table->json('channels');
            $table->string('recipient_scope', 20)->default('owner');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'asset_type']);
            $table->unique(['asset_type', 'days_before', 'recipient_scope'], 'reminder_rules_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_rules');
    }
};
