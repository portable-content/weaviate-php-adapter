<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PortableContent\Repository\WeaviateSchemaManager;
use PortableContent\Tests\Support\WeaviateTestHelper;
use Weaviate\WeaviateClient;

/**
 * Base class for Weaviate integration tests.
 * 
 * Provides common setup, teardown, and utilities for tests that require
 * a real Weaviate instance.
 */
abstract class WeaviateIntegrationTestCase extends TestCase
{
    protected WeaviateClient $client;
    protected string $testClassName;
    protected WeaviateSchemaManager $schemaManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip test if Weaviate is not available
        WeaviateTestHelper::skipIfWeaviateUnavailable();
        
        // Get unique test class name for this test
        $this->testClassName = WeaviateTestHelper::getTestClassName();
        
        // Get Weaviate client
        $this->client = WeaviateTestHelper::getClient();
        
        // Create schema manager
        $this->schemaManager = new WeaviateSchemaManager($this->client, $this->testClassName);
        
        // Clean up any existing schema
        $this->cleanupTestSchema();
        
        // Wait for consistency
        WeaviateTestHelper::waitForSchemaConsistency();
    }

    protected function tearDown(): void
    {
        // Clean up test schema
        $this->cleanupTestSchema();
        
        parent::tearDown();
    }

    /**
     * Create the test schema for this test.
     */
    protected function createTestSchema(): void
    {
        if (!$this->schemaManager->schemaExists()) {
            $this->schemaManager->createSchema();
            WeaviateTestHelper::waitForSchemaConsistency();
        }
    }

    /**
     * Clean up the test schema.
     */
    protected function cleanupTestSchema(): void
    {
        try {
            if ($this->schemaManager->schemaExists()) {
                $this->schemaManager->deleteSchema();
                WeaviateTestHelper::waitForSchemaConsistency();
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors in tearDown
        }
    }

    /**
     * Assert that a schema exists in Weaviate.
     */
    protected function assertSchemaExists(string $className): void
    {
        $this->assertTrue(
            $this->schemaManager->schemaExists($className),
            "Schema '{$className}' should exist in Weaviate"
        );
    }

    /**
     * Assert that a schema does not exist in Weaviate.
     */
    protected function assertSchemaNotExists(string $className): void
    {
        $this->assertFalse(
            $this->schemaManager->schemaExists($className),
            "Schema '{$className}' should not exist in Weaviate"
        );
    }

    /**
     * Get connection info for debugging.
     * 
     * @return array<string, mixed>
     */
    protected function getConnectionInfo(): array
    {
        return WeaviateTestHelper::getTestConnectionInfo();
    }

    /**
     * Wait for Weaviate to be ready.
     */
    protected function waitForWeaviate(int $maxWaitSeconds = 30): void
    {
        $start = time();
        
        while ((time() - $start) < $maxWaitSeconds) {
            if (WeaviateTestHelper::isWeaviateAvailable()) {
                return;
            }
            
            sleep(1);
        }
        
        $this->fail('Weaviate did not become available within the timeout period');
    }

    /**
     * Skip test if running in CI without Docker.
     */
    protected function skipIfNoDocker(): void
    {
        if (getenv('CI') === 'true' && !$this->isDockerAvailable()) {
            $this->markTestSkipped('Docker is not available in CI environment');
        }
    }

    /**
     * Check if Docker is available.
     */
    private function isDockerAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('docker --version 2>/dev/null', $output, $returnCode);
        
        return $returnCode === 0;
    }
}
