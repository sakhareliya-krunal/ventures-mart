<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar', 500)->nullable()->after('google_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite cannot easily drop NOT NULL without rebuild; recreate via temporary table.
            Schema::table('users', function (Blueprint $table) {
                $table->string('password_tmp')->nullable();
            });
            DB::table('users')->update([
                'password_tmp' => DB::raw('password'),
            ]);
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password');
            });
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('password_tmp', 'password');
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE users SET password = '' WHERE password IS NULL");
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE users SET password = '' WHERE password IS NULL");
            DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar']);
        });
    }
};
