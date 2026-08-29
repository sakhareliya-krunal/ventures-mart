<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_image', 500);
            $table->string('web_image', 500);
            $table->string('alt_text', 160)->default('Homepage banner');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('banners')->insert([
            [
                'mobile_image' => '/images/hero/carousel/optimized/toy-desktop.jpg',
                'web_image' => '/images/hero/carousel/optimized/toy-wide.jpg',
                'alt_text' => 'Toy collection hero image',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mobile_image' => '/images/hero/carousel/optimized/toy-2-desktop.jpg',
                'web_image' => '/images/hero/carousel/optimized/toy-2-wide.jpg',
                'alt_text' => 'Kids toy carousel image',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mobile_image' => '/images/hero/carousel/optimized/lunch-box-desktop.jpg',
                'web_image' => '/images/hero/carousel/optimized/lunch-box-wide.jpg',
                'alt_text' => 'Lunch box collection hero image',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mobile_image' => '/images/hero/carousel/optimized/lunch-box-2-desktop.jpg',
                'web_image' => '/images/hero/carousel/optimized/lunch-box-2-wide.jpg',
                'alt_text' => 'Kids lunch box carousel image',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mobile_image' => '/images/hero/carousel/optimized/toy-3-mobile.jpg',
                'web_image' => '/images/hero/carousel/optimized/toy-3-wide.jpg',
                'alt_text' => 'Kids toy carousel image',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'mobile_image' => '/images/hero/carousel/optimized/lunch-box-3-mobile.jpg',
                'web_image' => '/images/hero/carousel/optimized/lunch-box-3-wide.jpg',
                'alt_text' => 'Lunch box carousel image',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
