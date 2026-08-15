<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('alt_email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();

            // A renewal notice sent to the client turns a cost centre into an
            // invoice, but not every client wants one. Off unless asked for.
            $table->boolean('send_renewal_notices')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['is_archived', 'status']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
