<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('variant_group_id')->nullable()->after('stock');
            $table->string('color_name')->nullable()->after('variant_group_id');
            $table->string('color_hex', 7)->nullable()->after('color_name');
            $table->json('gallery')->nullable()->after('color_hex');
            $table->index('variant_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['variant_group_id']);
            $table->dropColumn(['variant_group_id', 'color_name', 'color_hex', 'gallery']);
        });
    }
};
