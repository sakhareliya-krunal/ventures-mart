<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('cgst', 10, 2)->default(0)->after('shipping');
            $table->decimal('sgst', 10, 2)->default(0)->after('cgst');
            $table->decimal('igst', 10, 2)->default(0)->after('sgst');
            $table->string('seller_state')->default('Gujarat')->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cgst', 'sgst', 'igst', 'seller_state']);
        });
    }
};
