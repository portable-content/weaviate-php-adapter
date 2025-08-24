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
            $port = $_ENV['WEAVIATE_TEST_PORT'] ?? '8082';

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
        } catch (\Exception) {
            return false;
        }
    }

    public static function skipIfWeaviateUnavailable(): void
    {
        if (!self::isWeaviateAvailable()) {
            throw new \PHPUnit\Framework\SkippedTestError(
                'Weaviate is not available. Please start Weaviate server for integration tests.'
            );
        }
    }

    public static function cleanupSchema(?string $className = null): void
    {
        $className = $className ?? self::getTestClassName();
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

    public static function waitForSchemaConsistency(int $maxWaitSeconds = 5): void
    {
        $start = time();
        
        while ((time() - $start) < $maxWaitSeconds) {
            try {
                $client = self::getClient();
                $client->schema()->get();
                
                // Small delay to ensure consistency
                usleep(100000); // 100ms
                
                return;
            } catch (\Exception) {
                usleep(200000); // 200ms
            }
        }
    }

    public static function createTestSchema(?string $className = null): WeaviateSchemaManager
    {
        $className = $className ?? self::getTestClassName();
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

    public static function getTestConnectionInfo(): array
    {
        $host = $_ENV['WEAVIATE_TEST_HOST'] ?? 'localhost';
        $port = $_ENV['WEAVIATE_TEST_PORT'] ?? '8082';
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
