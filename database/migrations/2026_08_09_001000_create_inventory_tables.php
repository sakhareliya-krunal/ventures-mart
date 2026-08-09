<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_balances')) {
            Schema::create('inventory_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('on_hand')->default(0);
                $table->unsignedBigInteger('reserved')->default(0);
                $table->unsignedBigInteger('committed')->default(0);
                $table->unsignedBigInteger('version')->default(0);
                $table->unsignedInteger('low_stock_threshold')->nullable();
                $table->unsignedInteger('reorder_point')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_ledger_entries')) {
            Schema::create('inventory_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 64);
                $table->bigInteger('on_hand_delta')->default(0);
                $table->bigInteger('reserved_delta')->default(0);
                $table->bigInteger('committed_delta')->default(0);
                $table->unsignedBigInteger('on_hand_balance');
                $table->unsignedBigInteger('reserved_balance');
                $table->unsignedBigInteger('committed_balance');
                $table->string('idempotency_key')->unique();
                $table->string('correlation_id')->nullable();
                $table->string('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['product_id', 'occurred_at']);
                $table->index(['order_id', 'occurred_at']);
                $table->index(['type', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('inventory_reservations')) {
            Schema::create('inventory_reservations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->string('state', 32);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('committed_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->string('release_reason')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'state']);
                $table->index(['product_id', 'state']);
                $table->index(['state', 'expires_at']);
            });
        }

        if (! Schema::hasTable('inventory_audit_flags')) {
            Schema::create('inventory_audit_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code', 64);
                $table->text('message');
                $table->json('context')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['code', 'resolved_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_flags');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_ledger_entries');
        Schema::dropIfExists('inventory_balances');
    }
};
