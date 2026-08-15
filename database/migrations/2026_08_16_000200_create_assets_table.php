<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->string('identifier')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_account')->nullable();

            $table->date('purchased_at')->nullable();

            // A date, never a datetime. As a timestamp this drifts across
            // timezones and "3 days before" fires on the wrong day.
            $table->date('expires_at')->index();

            $table->date('verified_expires_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verification_status', 16)->default('unchecked');

            $table->decimal('cost', 10, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('billing_cycle', 20)->nullable();
            $table->boolean('auto_renew')->default(false);

            $table->string('status', 16)->default('active');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('reminders_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            // The reminder engine's hot path: everything due in a window that
            // is still being watched.
            $table->index(['expires_at', 'status', 'is_archived'], 'assets_window_index');
            $table->index(['client_id', 'is_archived']);
            $table->index(['owner_id', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
