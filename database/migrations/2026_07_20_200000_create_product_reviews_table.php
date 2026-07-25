<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        DB::table('products')->update([
            'rating' => 0,
            'reviews' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
