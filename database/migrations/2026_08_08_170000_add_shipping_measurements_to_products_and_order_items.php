<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('weight_kg', 8, 3)->nullable()->after('stock');
            $table->decimal('length_cm', 8, 2)->nullable()->after('weight_kg');
            $table->decimal('breadth_cm', 8, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 8, 2)->nullable()->after('breadth_cm');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('weight_kg', 8, 3)->nullable()->after('quantity');
            $table->decimal('length_cm', 8, 2)->nullable()->after('weight_kg');
            $table->decimal('breadth_cm', 8, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 8, 2)->nullable()->after('breadth_cm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['weight_kg', 'length_cm', 'breadth_cm', 'height_cm']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['weight_kg', 'length_cm', 'breadth_cm', 'height_cm']);
        });
    }
};
