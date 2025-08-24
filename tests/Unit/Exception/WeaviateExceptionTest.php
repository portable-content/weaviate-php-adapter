<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Exception;

use PortableContent\Exception\RepositoryException;
use PortableContent\Exception\WeaviateException;
use PortableContent\Tests\TestCase;

final class WeaviateExceptionTest extends TestCase
{
    public function testExtendsRepositoryException(): void
    {
        $exception = WeaviateException::connectionFailed('localhost', 8080, 'Connection refused');

        $this->assertInstanceOf(RepositoryException::class, $exception);
    }

    public function testConnectionFailed(): void
    {
        $exception = WeaviateException::connectionFailed('localhost', 8080, 'Connection refused');

        $this->assertSame(
            'Failed to connect to Weaviate at localhost:8080: Connection refused',
            $exception->getMessage()
        );
    }

    public function testSchemaCreationFailed(): void
    {
        $exception = WeaviateException::schemaCreationFailed('ContentItem', 'Invalid property');

        $this->assertSame(
            'Failed to create schema for class "ContentItem": Invalid property',
            $exception->getMessage()
        );
    }

    public function testSchemaValidationFailed(): void
    {
        $exception = WeaviateException::schemaValidationFailed('ContentItem', 'Missing property');

        $this->assertSame(
            'Schema validation failed for class "ContentItem": Missing property',
            $exception->getMessage()
        );
    }

    public function testSchemaDeletionFailed(): void
    {
        $exception = WeaviateException::schemaDeletionFailed('ContentItem', 'Class not found');

        $this->assertSame(
            'Failed to delete schema for class "ContentItem": Class not found',
            $exception->getMessage()
        );
    }

    public function testQueryFailed(): void
    {
        $exception = WeaviateException::queryFailed('findById', 'Invalid ID format');

        $this->assertSame(
            'Weaviate query failed for operation "findById": Invalid ID format',
            $exception->getMessage()
        );
    }

    public function testInvalidResponse(): void
    {
        $exception = WeaviateException::invalidResponse('save', 'Missing required field');

        $this->assertSame(
            'Invalid response from Weaviate for operation "save": Missing required field',
            $exception->getMessage()
        );
    }

    public function testAuthenticationFailed(): void
    {
        $exception = WeaviateException::authenticationFailed('Invalid API key');

        $this->assertSame(
            'Weaviate authentication failed: Invalid API key',
            $exception->getMessage()
        );
    }

    public function testConfigurationError(): void
    {
        $exception = WeaviateException::configurationError('Missing host configuration');

        $this->assertSame(
            'Weaviate configuration error: Missing host configuration',
            $exception->getMessage()
        );
    }

    public function testClientError(): void
    {
        $exception = WeaviateException::clientError('save', 'Bad request format');

        $this->assertSame(
            'Weaviate client error during "save": Bad request format',
            $exception->getMessage()
        );
    }

    public function testServerError(): void
    {
        $exception = WeaviateException::serverError('findAll', 500, 'Internal server error');

        $this->assertSame(
            'Weaviate server error during "findAll" (HTTP 500): Internal server error',
            $exception->getMessage()
        );
    }

    public function testTimeoutError(): void
    {
        $exception = WeaviateException::timeoutError('search', 30);

        $this->assertSame(
            'Weaviate operation "search" timed out after 30 seconds',
            $exception->getMessage()
        );
    }

    public function testDataMapping(): void
    {
        $exception = WeaviateException::dataMapping('hydration', 'Invalid date format');

        $this->assertSame(
            'Data mapping error during "hydration": Invalid date format',
            $exception->getMessage()
        );
    }

    public function testSchemaExists(): void
    {
        $exception = WeaviateException::schemaExists('ContentItem');

        $this->assertSame(
            'Schema for class "ContentItem" already exists',
            $exception->getMessage()
        );
    }

    public function testSchemaNotFound(): void
    {
        $exception = WeaviateException::schemaNotFound('ContentItem');

        $this->assertSame(
            'Schema for class "ContentItem" not found',
            $exception->getMessage()
        );
    }

    public function testInvalidClassName(): void
    {
        $exception = WeaviateException::invalidClassName('invalid-name', 'Contains hyphens');

        $this->assertSame(
            'Invalid class name "invalid-name": Contains hyphens',
            $exception->getMessage()
        );
    }

    public function testUnsupportedOperation(): void
    {
        $exception = WeaviateException::unsupportedOperation('vectorSearch');

        $this->assertSame(
            'Unsupported operation: vectorSearch',
            $exception->getMessage()
        );
    }
}
