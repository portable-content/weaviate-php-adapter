<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration\Repository;

use PortableContent\Exception\WeaviateException;
use PortableContent\Repository\WeaviateSchemaManager;
use PortableContent\Tests\Integration\WeaviateIntegrationTestCase;
use PortableContent\Tests\Support\WeaviateTestHelper;

/**
 * Integration tests for WeaviateSchemaManager.
 *
 * These tests verify schema creation, validation, and management
 * operations against a real Weaviate instance.
 */
final class WeaviateSchemaManagerIntegrationTest extends WeaviateIntegrationTestCase
{
    public function testCreateSchemaSuccess(): void
    {
        $this->assertFalse($this->schemaManager->schemaExists());

        $this->schemaManager->createSchema();

        $this->assertTrue($this->schemaManager->schemaExists());
    }

    public function testCreateSchemaAlreadyExists(): void
    {
        // Create schema first
        $this->schemaManager->createSchema();
        $this->assertTrue($this->schemaManager->schemaExists());

        // Try to create again
        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('already exists');

        $this->schemaManager->createSchema();
    }

    public function testDeleteSchemaSuccess(): void
    {
        // Create schema first
        $this->schemaManager->createSchema();
        $this->assertTrue($this->schemaManager->schemaExists());

        // Delete it
        $this->schemaManager->deleteSchema();

        WeaviateTestHelper::waitForSchemaConsistency();

        $this->assertFalse($this->schemaManager->schemaExists());
    }

    public function testDeleteSchemaNonExistent(): void
    {
        $this->assertFalse($this->schemaManager->schemaExists());

        // Should not throw exception
        $this->schemaManager->deleteSchema();

        $this->assertFalse($this->schemaManager->schemaExists());
    }

    public function testSchemaExistsAfterCreation(): void
    {
        $this->assertFalse($this->schemaManager->schemaExists());

        $this->schemaManager->createSchema();

        $this->assertTrue($this->schemaManager->schemaExists());
    }

    public function testSchemaExistsWithCustomClassName(): void
    {
        $this->schemaManager->createSchema();

        $this->assertTrue($this->schemaManager->schemaExists($this->testClassName));
        $this->assertFalse($this->schemaManager->schemaExists('NonExistentClass'));
    }

    public function testGetSchemaAfterCreation(): void
    {
        $this->schemaManager->createSchema();

        $schema = $this->schemaManager->getSchema();

        $this->assertNotNull($schema);
        $this->assertIsArray($schema);
        $this->assertSame($this->testClassName, $schema['class']);
        $this->assertArrayHasKey('properties', $schema);

        $properties = $schema['properties'];
        $this->assertIsArray($properties);

        // Check that all expected properties exist
        $propertyNames = array_column($properties, 'name');
        $expectedProperties = [
            'contentId', 'type', 'title', 'summary',
            'createdAt', 'updatedAt', 'blockCount', 'blocks',
        ];

        foreach ($expectedProperties as $expectedProperty) {
            $this->assertContains($expectedProperty, $propertyNames);
        }
    }

    public function testGetSchemaNonExistent(): void
    {
        $this->assertFalse($this->schemaManager->schemaExists());

        $schema = $this->schemaManager->getSchema();

        $this->assertNull($schema);
    }

    public function testValidateSchemaSuccess(): void
    {
        $this->schemaManager->createSchema();

        $this->assertTrue($this->schemaManager->validateSchema());
    }

    public function testValidateSchemaNotFound(): void
    {
        $this->assertFalse($this->schemaManager->schemaExists());

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('not found');

        $this->schemaManager->validateSchema();
    }

    public function testSchemaStructure(): void
    {
        $this->schemaManager->createSchema();

        $schema = $this->schemaManager->getSchema();

        // Verify schema structure
        $this->assertNotNull($schema);
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('class', $schema);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('description', $schema);

        // Verify properties
        $properties = $schema['properties'];
        $this->assertIsArray($properties);
        $this->assertCount(8, $properties);

        // Check specific property structures
        $propertyMap = [];
        foreach ($properties as $property) {
            $this->assertIsArray($property);
            $this->assertArrayHasKey('name', $property);
            $propertyMap[$property['name']] = $property;
        }

        // contentId property
        $this->assertArrayHasKey('contentId', $propertyMap);
        $this->assertSame(['text'], $propertyMap['contentId']['dataType']);

        // type property
        $this->assertArrayHasKey('type', $propertyMap);
        $this->assertSame(['text'], $propertyMap['type']['dataType']);

        // createdAt property
        $this->assertArrayHasKey('createdAt', $propertyMap);
        $this->assertSame(['date'], $propertyMap['createdAt']['dataType']);

        // blockCount property
        $this->assertArrayHasKey('blockCount', $propertyMap);
        $this->assertSame(['int'], $propertyMap['blockCount']['dataType']);

        // blocks property
        $this->assertArrayHasKey('blocks', $propertyMap);
        $this->assertSame(['text'], $propertyMap['blocks']['dataType']);
    }

    public function testMultipleSchemaOperations(): void
    {
        // Create
        $this->schemaManager->createSchema();
        $this->assertTrue($this->schemaManager->schemaExists());

        // Validate
        $this->assertTrue($this->schemaManager->validateSchema());

        // Get
        $schema = $this->schemaManager->getSchema();
        $this->assertNotNull($schema);

        // Delete
        $this->schemaManager->deleteSchema();
        WeaviateTestHelper::waitForSchemaConsistency();
        $this->assertFalse($this->schemaManager->schemaExists());

        // Get after delete
        $schema = $this->schemaManager->getSchema();
        $this->assertNull($schema);
    }

    public function testSchemaWithDifferentClassNames(): void
    {
        // Test with different class names
        $className1 = 'TestClass1' . uniqid();
        $className2 = 'TestClass2' . uniqid();

        $client = WeaviateTestHelper::getClient();
        $manager1 = new WeaviateSchemaManager($client, $className1);
        $manager2 = new WeaviateSchemaManager($client, $className2);

        try {
            // Create schemas with different class names
            $manager1->createSchema();
            $manager2->createSchema();

            // Both should exist
            $this->assertTrue($manager1->schemaExists());
            $this->assertTrue($manager2->schemaExists());

            // They should be different classes
            $this->assertNotSame($className1, $className2);

            // Clean up
            $manager1->deleteSchema();
            $manager2->deleteSchema();
        } catch (\Exception $e) {
            // Clean up on failure
            try {
                $manager1->deleteSchema();
                $manager2->deleteSchema();
            } catch (\Exception) {
                // Ignore cleanup errors
            }

            throw $e;
        }
    }
}
