<?php

declare(strict_types=1);

namespace PortableContent\Tests\Support;

use PortableContent\Repository\WeaviateSchemaManager;
use Weaviate\WeaviateClient;

/**
 * Helper class for Weaviate integration tests.
 */
final class WeaviateTestHelper
{
    private static ?WeaviateClient $client = null;
    private static ?string $testClassName = null;

    public static function getTestClassName(): string
    {
        if (null === self::$testClassName) {
            self::$testClassName = 'Test' . uniqid();
        }

        return self::$testClassName;
    }

    public static function getClient(): WeaviateClient
    {
        if (null === self::$client) {
            $host = $_ENV['WEAVIATE_TEST_HOST'] ?? 'localhost';
            // Use port 8080 in CI, 8082 locally (to avoid conflicts with local Weaviate)
            $defaultPort = getenv('CI') === 'true' ? '8080' : '8082';
            $port = $_ENV['WEAVIATE_TEST_PORT'] ?? $defaultPort;

            self::$client = WeaviateClient::connectToLocal($host . ':' . $port);
        }

        return self::$client;
    }

    public static function isWeaviateAvailable(): bool
    {
        try {
            $client = self::getClient();

            // Try to get schema to test connectivity
            $client->schema()->get();

            return true;
        } catch (\Exception $e) {
            // Log the error for debugging in CI
            error_log("Weaviate availability check failed: " . $e->getMessage());

            return false;
        }
    }

    public static function skipIfWeaviateUnavailable(): void
    {
        if (!self::isWeaviateAvailable()) {
            $connectionInfo = self::getTestConnectionInfo();
            $baseUrl = isset($connectionInfo['base_url']) && is_string($connectionInfo['base_url'])
                ? $connectionInfo['base_url']
                : 'unknown';

            throw new \PHPUnit\Framework\SkippedTestSuiteError(
                "Weaviate is not available at {$baseUrl}. "
                . "Please start Weaviate server for integration tests using: "
                . "docker-compose -f docker-compose.test.yml up -d"
            );
        }
    }

    public static function cleanupSchema(?string $className = null): void
    {
        $className ??= self::getTestClassName();
        $client = self::getClient();

        try {
            $schemaManager = new WeaviateSchemaManager($client, $className);
            if ($schemaManager->schemaExists()) {
                $schemaManager->deleteSchema();
            }
        } catch (\Exception) {
            // Ignore cleanup errors
        }
    }

    public static function waitForSchemaConsistency(int $maxWaitSeconds = 10): void
    {
        $start = time();

        while ((time() - $start) < $maxWaitSeconds) {
            try {
                $client = self::getClient();
                $client->schema()->get();

                // Small delay to ensure consistency
                usleep(500000); // 500ms

                return;
            } catch (\Exception $e) {
                // Log for debugging
                error_log("Schema consistency check failed: " . $e->getMessage());
                sleep(1); // Wait 1 second before retry
            }
        }
    }

    public static function createTestSchema(?string $className = null): WeaviateSchemaManager
    {
        $className ??= self::getTestClassName();
        $client = self::getClient();

        $schemaManager = new WeaviateSchemaManager($client, $className);

        // Clean up any existing schema first
        self::cleanupSchema($className);

        // Wait for consistency
        self::waitForSchemaConsistency();

        // Create new schema
        $schemaManager->createSchema();

        // Wait for schema to be available
        self::waitForSchemaConsistency();

        return $schemaManager;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getTestConnectionInfo(): array
    {
        $host = $_ENV['WEAVIATE_TEST_HOST'] ?? 'localhost';
        // Use port 8080 in CI, 8082 locally (to avoid conflicts with local Weaviate)
        $defaultPort = getenv('CI') === 'true' ? '8080' : '8082';
        $port = $_ENV['WEAVIATE_TEST_PORT'] ?? $defaultPort;
        $scheme = $_ENV['WEAVIATE_TEST_SCHEME'] ?? 'http';

        return [
            'host' => $host,
            'port' => (int) $port,
            'scheme' => $scheme,
            'base_url' => $scheme . '://' . $host . ':' . $port,
            'timeout' => 10,
        ];
    }

    public static function reset(): void
    {
        self::cleanupSchema();
        self::$client = null;
        self::$testClassName = null;
    }
}
