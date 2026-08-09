<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_replacement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('status', 32)->default('requested');
            $table->string('reason', 32);
            $table->text('notes')->nullable();
            $table->json('photo_paths')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('replacement_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_replacement_requests');
    }
};
