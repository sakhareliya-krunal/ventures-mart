<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CopySqliteToMysql extends Command
{
    protected $signature = 'db:copy-sqlite-to-mysql
                            {--force : Truncate MySQL tables even if they already contain data}
                            {--chunk=200 : Rows per insert batch}';

    protected $description = 'Copy application data from the sqlite connection into MySQL (preserves IDs)';

    /**
     * Parent → child order for FK safety.
     *
     * @var list<string>
     */
    private array $tables = [
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'personal_access_tokens',
        'categories',
        'products',
        'cart_items',
        'wishlist_items',
        'product_reviews',
        'orders',
        'order_items',
        'posts',
        'contact_messages',
        'addresses',
        'migrations',
    ];

    /**
     * Columns that should be stored as JSON on MySQL.
     *
     * @var array<string, list<string>>
     */
    private array $jsonColumns = [
        'products' => ['tags', 'details', 'specifications', 'gallery'],
    ];

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $force = (bool) $this->option('force');

        try {
            DB::connection('sqlite')->getPdo();
        } catch (Throwable $e) {
            $this->error('Unable to connect to sqlite source: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            DB::connection('mysql')->getPdo();
        } catch (Throwable $e) {
            $this->error('Unable to connect to mysql target: '.$e->getMessage());

            return self::FAILURE;
        }

        $mysql = DB::connection('mysql');

        if (! $force) {
            foreach ($this->tables as $table) {
                if (! Schema::connection('mysql')->hasTable($table)) {
                    continue;
                }

                if ($mysql->table($table)->exists()) {
                    $this->error("MySQL table [{$table}] is not empty. Re-run with --force to truncate and replace.");

                    return self::FAILURE;
                }
            }
        }

        $mysql->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (array_reverse($this->tables) as $table) {
                if (! Schema::connection('mysql')->hasTable($table)) {
                    continue;
                }

                $mysql->table($table)->truncate();
            }

            foreach ($this->tables as $table) {
                $this->copyTable($table, $chunk);
            }

            foreach ($this->tables as $table) {
                $this->resetAutoIncrement($table);
            }
        } finally {
            $mysql->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('SQLite → MySQL copy complete.');

        return self::SUCCESS;
    }

    private function copyTable(string $table, int $chunk): void
    {
        if (! Schema::connection('sqlite')->hasTable($table)) {
            $this->warn("Skipping missing sqlite table [{$table}].");

            return;
        }

        if (! Schema::connection('mysql')->hasTable($table)) {
            $this->warn("Skipping missing mysql table [{$table}].");

            return;
        }

        $sqlite = DB::connection('sqlite');
        $mysql = DB::connection('mysql');

        $sourceColumns = Schema::connection('sqlite')->getColumnListing($table);
        $targetColumns = Schema::connection('mysql')->getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));

        if ($columns === []) {
            $this->warn("Skipping [{$table}] (no shared columns).");

            return;
        }

        $jsonColumns = $this->jsonColumns[$table] ?? [];
        $total = 0;
        $offset = 0;

        while (true) {
            $rows = $sqlite->table($table)
                ->offset($offset)
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $payload = [];
            foreach ($rows as $row) {
                $item = [];
                foreach ($columns as $column) {
                    $value = $row->{$column} ?? null;

                    if (in_array($column, $jsonColumns, true)) {
                        $value = $this->normalizeJson($value);
                    }

                    $item[$column] = $value;
                }
                $payload[] = $item;
            }

            $mysql->table($table)->insert($payload);
            $count = count($payload);
            $total += $count;
            $offset += $count;

            if ($count < $chunk) {
                break;
            }
        }

        $this->line("Copied {$total} row(s) → {$table}");
    }

    private function normalizeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded);
            }
        }

        return $value;
    }

    private function resetAutoIncrement(string $table): void
    {
        if (! Schema::connection('mysql')->hasTable($table)) {
            return;
        }

        if (! Schema::connection('mysql')->hasColumn($table, 'id')) {
            return;
        }

        $max = DB::connection('mysql')->table($table)->max('id');
        if ($max === null || ! is_numeric($max)) {
            return;
        }

        $next = ((int) $max) + 1;
        DB::connection('mysql')->statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
    }
}
