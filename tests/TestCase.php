<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use PHPUnit\Framework\AssertionFailedError;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->ensureSafeMysqlTestDatabase();

        parent::setUp();
    }

    private function ensureSafeMysqlTestDatabase(): void
    {
        if ($this->testEnv('DB_CONNECTION', 'mysql') !== 'mysql') {
            return;
        }

        $database = $this->testEnv('DB_DATABASE', '');

        if (! preg_match('/^[A-Za-z0-9_]+_test$/', $database)) {
            throw new AssertionFailedError(
                'MySQL tests require a dedicated database name ending in "_test".'
            );
        }

        $host = $this->testEnv('DB_HOST', '127.0.0.1');
        $port = $this->testEnv('DB_PORT', '3306');
        $username = $this->testEnv('DB_USERNAME', 'root');
        $password = $this->testEnv('DB_PASSWORD', '');

        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function testEnv(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value !== false && $value !== null && $value !== '') {
            return (string) $value;
        }

        static $dotEnv = null;

        if ($dotEnv === null) {
            $path = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';
            $dotEnv = [];

            if (is_file($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);

                    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                        continue;
                    }

                    [$name, $rawValue] = explode('=', $line, 2);
                    $dotEnv[trim($name)] = trim(trim($rawValue), "\"'");
                }
            }
        }

        $value = $dotEnv[$key] ?? null;

        return $value !== null && $value !== '' ? (string) $value : $default;
    }
}
