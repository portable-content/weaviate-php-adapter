<?php

declare(strict_types=1);

namespace PortableContent\Exception;

use PortableContent\Exception\RepositoryException;

/**
 * Exception thrown when Weaviate operations fail.
 */
class WeaviateException extends RepositoryException
{
    public static function connectionFailed(string $host, int $port, string $reason): self
    {
        return new self(
            sprintf('Failed to connect to Weaviate at %s:%d: %s', $host, $port, $reason)
        );
    }

    public static function schemaCreationFailed(string $className, string $reason): self
    {
        return new self(
            sprintf('Failed to create schema for class "%s": %s', $className, $reason)
        );
    }

    public static function schemaValidationFailed(string $className, string $reason): self
    {
        return new self(
            sprintf('Schema validation failed for class "%s": %s', $className, $reason)
        );
    }

    public static function schemaDeletionFailed(string $className, string $reason): self
    {
        return new self(
            sprintf('Failed to delete schema for class "%s": %s', $className, $reason)
        );
    }

    public static function queryFailed(string $operation, string $reason): self
    {
        return new self(
            sprintf('Weaviate query failed for operation "%s": %s', $operation, $reason)
        );
    }

    public static function invalidResponse(string $operation, string $reason): self
    {
        return new self(
            sprintf('Invalid response from Weaviate for operation "%s": %s', $operation, $reason)
        );
    }

    public static function authenticationFailed(string $reason): self
    {
        return new self(
            sprintf('Weaviate authentication failed: %s', $reason)
        );
    }

    public static function configurationError(string $reason): self
    {
        return new self(
            sprintf('Weaviate configuration error: %s', $reason)
        );
    }

    public static function clientError(string $operation, string $reason): self
    {
        return new self(
            sprintf('Weaviate client error during "%s": %s', $operation, $reason)
        );
    }

    public static function serverError(string $operation, int $statusCode, string $reason): self
    {
        return new self(
            sprintf('Weaviate server error during "%s" (HTTP %d): %s', $operation, $statusCode, $reason)
        );
    }

    public static function timeoutError(string $operation, int $timeout): self
    {
        return new self(
            sprintf('Weaviate operation "%s" timed out after %d seconds', $operation, $timeout)
        );
    }

    public static function dataMapping(string $operation, string $reason): self
    {
        return new self(
            sprintf('Data mapping error during "%s": %s', $operation, $reason)
        );
    }

    public static function schemaExists(string $className): self
    {
        return new self(
            sprintf('Schema for class "%s" already exists', $className)
        );
    }

    public static function schemaNotFound(string $className): self
    {
        return new self(
            sprintf('Schema for class "%s" not found', $className)
        );
    }

    public static function invalidClassName(string $className, string $reason): self
    {
        return new self(
            sprintf('Invalid class name "%s": %s', $className, $reason)
        );
    }

    public static function unsupportedOperation(string $operation): self
    {
        return new self(
            sprintf('Unsupported operation: %s', $operation)
        );
    }
}
