<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_errors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('level', 32)->default('error');
            $table->text('message');
            $table->string('exception_class')->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->longText('trace')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('resolved_at');
            $table->index('created_at');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_errors');
    }
};
