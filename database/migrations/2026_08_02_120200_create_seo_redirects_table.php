<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_path', 500)->unique();
            $table->string('target_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
    }
};
