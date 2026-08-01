<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->unique()->after('number');
            $table->timestamp('invoice_issued_at')->nullable()->after('invoice_number');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn', 16)->nullable()->after('sku');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('hsn', 16)->nullable()->after('product_sku');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoice_issued_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hsn');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('hsn');
        });
    }
};
