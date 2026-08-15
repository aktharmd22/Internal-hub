<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reminder_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('days_before');
            $table->string('channel', 20);
            $table->string('recipient_type', 20);
            $table->unsignedBigInteger('recipient_id');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 16)->default('sent');
            $table->text('error')->nullable();
            $table->timestamps();

            /*
             * The single most important constraint in the system.
             *
             * Application logic decides what to send; this index is what makes
             * it impossible to send twice. It survives a retried queue job, a
             * double-fired scheduler and two servers racing each other — none
             * of which application logic alone can defend against.
             *
             * `recipient_type` is part of the key deliberately: without it,
             * user #5 and client #5 collide on the same asset and the client's
             * renewal notice is silently swallowed — exactly the kind of quiet
             * failure this system exists to prevent.
             */
            $table->unique(
                ['asset_id', 'days_before', 'channel', 'recipient_type', 'recipient_id'],
                'reminder_logs_idempotency'
            );

            $table->index(['asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
