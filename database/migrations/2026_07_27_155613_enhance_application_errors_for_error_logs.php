<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_errors', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable()->after('uuid');
            $table->unsignedInteger('occurrence_count')->default(1)->after('fingerprint');
            $table->string('category', 32)->default('exception')->after('occurrence_count');
            $table->string('status', 32)->default('new')->after('category');
            $table->string('url')->nullable()->after('context');
            $table->string('route')->nullable()->after('url');
            $table->string('method', 16)->nullable()->after('route');
            $table->string('ip', 45)->nullable()->after('method');
            $table->foreignId('user_id')->nullable()->after('ip')->constrained()->nullOnDelete();
            $table->text('user_agent')->nullable()->after('user_id');
            $table->json('request')->nullable()->after('user_agent');
            $table->timestamp('first_seen_at')->nullable()->after('resolved_at');
            $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');

            $table->index('fingerprint');
            $table->index('status');
            $table->index('category');
            $table->index('last_seen_at');
        });

        DB::table('application_errors')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $context = [];
                if (! empty($row->context)) {
                    $decoded = json_decode($row->context, true);
                    if (is_array($decoded)) {
                        $context = $decoded;
                    }
                }

                $fingerprint = hash(
                    'xxh128',
                    ($row->exception_class ?: 'log').'|'.($row->message ?: '').'|'.($row->file ?: '').'|'.($row->line ?: '')
                );

                $status = $row->resolved_at ? 'resolved' : 'new';
                $seen = $row->created_at ?: now();

                DB::table('application_errors')->where('id', $row->id)->update([
                    'fingerprint' => $fingerprint,
                    'occurrence_count' => 1,
                    'category' => $row->exception_class ? 'exception' : 'system',
                    'status' => $status,
                    'url' => $context['url'] ?? null,
                    'method' => $context['method'] ?? null,
                    'ip' => $context['ip'] ?? null,
                    'user_id' => $context['user_id'] ?? null,
                    'first_seen_at' => $seen,
                    'last_seen_at' => $seen,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('application_errors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropIndex(['fingerprint']);
            $table->dropIndex(['status']);
            $table->dropIndex(['category']);
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn([
                'fingerprint',
                'occurrence_count',
                'category',
                'status',
                'url',
                'route',
                'method',
                'ip',
                'user_agent',
                'request',
                'first_seen_at',
                'last_seen_at',
            ]);
        });
    }
};
