<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('seoable');
            $table->string('page_key')->nullable();
            $table->string('locale', 12)->default('en-IN');
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('focus_keyword')->nullable();
            $table->string('seo_slug')->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('meta_robots', 120)->default('index,follow');
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image', 500)->nullable();
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image', 500)->nullable();
            $table->string('image_alt_text')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('ai_highlights')->nullable();
            $table->json('custom_schema')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_metadata_seoable_locale_unique');
            $table->unique(['page_key', 'locale'], 'seo_metadata_page_locale_unique');
            $table->index(['locale', 'meta_robots']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
