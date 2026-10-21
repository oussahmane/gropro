<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // No ->after(): MySQL-only column ordering; PostgreSQL ignores/rejects it.
            $table->unsignedInteger('content_revision')->default(0);
        });

        Schema::create('order_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('endpoint', 32);
            $table->string('key', 64);
            $table->unsignedSmallInteger('status_code');
            $table->json('response_body');
            $table->timestamps();

            $table->unique(['order_id', 'user_id', 'endpoint', 'key'], 'order_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_idempotency_keys');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('content_revision');
        });
    }
};
