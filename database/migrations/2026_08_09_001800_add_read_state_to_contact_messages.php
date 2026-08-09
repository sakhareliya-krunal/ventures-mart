<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->index()->after('message');
            $table->foreignId('read_by_user_id')
                ->nullable()
                ->after('read_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('contact_messages')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('read_by_user_id');
            $table->dropColumn('read_at');
        });
    }
};
