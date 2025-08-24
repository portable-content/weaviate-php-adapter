<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Repository;

use PHPUnit\Framework\MockObject\MockObject;
use PortableContent\Exception\WeaviateException;
use PortableContent\Repository\WeaviateSchemaManager;
use PortableContent\Tests\TestCase;
use Weaviate\Schema\Schema;
use Weaviate\WeaviateClient;

final class WeaviateSchemaManagerTest extends TestCase
{
    private const TEST_CLASS_NAME = 'TestContentItem';

    private WeaviateClient&MockObject $client;
    private WeaviateSchemaManager $schemaManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(WeaviateClient::class);
        $this->schemaManager = new WeaviateSchemaManager($this->client, self::TEST_CLASS_NAME);
    }

    public function testCreateSchemaSuccess(): void
    {
        $schemaMock = $this->createMock(Schema::class);

        $this->client->expects($this->exactly(2))
            ->method('schema')
            ->willReturn($schemaMock);

        // First check if schema exists (should return false)
        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(false);

        // Then create the schema
        $schemaMock->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($schema) {
                return $schema['class'] === self::TEST_CLASS_NAME
                    && isset($schema['properties'])
                    && is_array($schema['properties']);
            }))
            ->willReturn(['class' => self::TEST_CLASS_NAME]);

        $this->schemaManager->createSchema();
    }

    public function testCreateSchemaAlreadyExists(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->once())
            ->method('schema')
            ->willReturn($schemaMock);

        // Schema exists
        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(true);

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Schema for class "TestContentItem" already exists');

        $this->schemaManager->createSchema();
    }

    public function testCreateSchemaFailure(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->exactly(2))
            ->method('schema')
            ->willReturn($schemaMock);

        // Schema doesn't exist
        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(false);

        // Create fails
        $schemaMock->expects($this->once())
            ->method('create')
            ->willThrowException(new \Exception('Connection failed'));

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Failed to create schema for class "TestContentItem": Connection failed');

        $this->schemaManager->createSchema();
    }

    public function testDeleteSchemaSuccess(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->exactly(2))
            ->method('schema')
            ->willReturn($schemaMock);

        // Schema exists
        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(true);

        // Delete succeeds
        $schemaMock->expects($this->once())
            ->method('delete')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(true);

        $this->schemaManager->deleteSchema();
    }

    public function testDeleteSchemaNotExists(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->once())
            ->method('schema')
            ->willReturn($schemaMock);

        // Schema doesn't exist
        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(false);

        // Delete should not be called
        $schemaMock->expects($this->never())
            ->method('delete');

        $this->schemaManager->deleteSchema();
    }

    public function testSchemaExistsTrue(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->once())
            ->method('schema')
            ->willReturn($schemaMock);

        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(true);

        $this->assertTrue($this->schemaManager->schemaExists());
    }

    public function testSchemaExistsFalse(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->once())
            ->method('schema')
            ->willReturn($schemaMock);

        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(false);

        $this->assertFalse($this->schemaManager->schemaExists());
    }

    public function testSchemaExistsWithCustomClassName(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->once())
            ->method('schema')
            ->willReturn($schemaMock);

        $schemaMock->expects($this->once())
            ->method('exists')
            ->with('CustomClass')
            ->willReturn(true);

        $this->assertTrue($this->schemaManager->schemaExists('CustomClass'));
    }

    public function testValidateSchemaSuccess(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->exactly(2))
            ->method('schema')
            ->willReturn($schemaMock);

        // First check if schema exists
        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(true);

        $existingSchema = [
            'class' => 'TestContentItem',
            'properties' => [
                ['name' => 'contentId', 'dataType' => ['text']],
                ['name' => 'type', 'dataType' => ['text']],
                ['name' => 'title', 'dataType' => ['text']],
                ['name' => 'summary', 'dataType' => ['text']],
                ['name' => 'createdAt', 'dataType' => ['date']],
                ['name' => 'updatedAt', 'dataType' => ['date']],
                ['name' => 'blockCount', 'dataType' => ['int']],
                ['name' => 'blocks', 'dataType' => ['text']],
            ],
        ];

        $schemaMock->expects($this->once())
            ->method('get')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn($existingSchema);

        $this->assertTrue($this->schemaManager->validateSchema());
    }

    public function testValidateSchemaNotFound(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->once())
            ->method('schema')
            ->willReturn($schemaMock);

        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(false);

        $this->expectException(WeaviateException::class);
        $this->expectExceptionMessage('Schema for class "TestContentItem" not found');

        $this->schemaManager->validateSchema();
    }

    public function testGetSchemaSuccess(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->exactly(2))
            ->method('schema')
            ->willReturn($schemaMock);

        // First check if schema exists
        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(true);

        $expectedClass = [
            'class' => 'TestContentItem',
            'properties' => [],
        ];

        $schemaMock->expects($this->once())
            ->method('get')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn($expectedClass);

        $result = $this->schemaManager->getSchema();

        $this->assertSame($expectedClass, $result);
    }

    public function testGetSchemaNotFound(): void
    {
        $schemaMock = $this->createMock(\Weaviate\Schema\Schema::class);

        $this->client->expects($this->once())
            ->method('schema')
            ->willReturn($schemaMock);

        $schemaMock->expects($this->once())
            ->method('exists')
            ->with(self::TEST_CLASS_NAME)
            ->willReturn(false);

        $result = $this->schemaManager->getSchema();

        $this->assertNull($result);
    }
}
