<?php

declare(strict_types=1);

namespace PortableContent\Repository;

use PortableContent\Exception\WeaviateException;
use Weaviate\WeaviateClient;

/**
 * Manages Weaviate schema creation and validation for ContentItem storage.
 */
final class WeaviateSchemaManager
{
    private const DEFAULT_CLASS_NAME = 'ContentItem';

    public function __construct(
        private readonly WeaviateClient $client,
        private readonly string $className = self::DEFAULT_CLASS_NAME
    ) {
    }

    /**
     * Create the ContentItem schema in Weaviate.
     */
    public function createSchema(): void
    {
        if ($this->schemaExists()) {
            throw WeaviateException::schemaExists($this->className);
        }

        try {
            $schema = $this->buildContentItemSchema();
            $this->client->schema()->create($schema);
        } catch (\Exception $e) {
            if ($e instanceof WeaviateException) {
                throw $e;
            }

            throw WeaviateException::schemaCreationFailed($this->className, $e->getMessage());
        }
    }

    /**
     * Delete the ContentItem schema from Weaviate.
     */
    public function deleteSchema(): void
    {
        if (!$this->schemaExists()) {
            return; // Schema doesn't exist, nothing to delete
        }

        try {
            $this->client->schema()->delete($this->className);
        } catch (\Exception $e) {
            if ($e instanceof WeaviateException) {
                throw $e;
            }

            throw WeaviateException::schemaDeletionFailed($this->className, $e->getMessage());
        }
    }

    /**
     * Check if the ContentItem schema exists in Weaviate.
     */
    public function schemaExists(?string $className = null): bool
    {
        $className = $className ?? $this->className;

        try {
            return $this->client->schema()->exists($className);
        } catch (\Exception $e) {
            throw WeaviateException::queryFailed('schema_exists', $e->getMessage());
        }
    }

    /**
     * Validate that the existing schema matches our expected structure.
     */
    public function validateSchema(): bool
    {
        if (!$this->schemaExists()) {
            throw WeaviateException::schemaNotFound($this->className);
        }

        try {
            $existingSchema = $this->client->schema()->get($this->className);
            $expectedSchema = $this->buildContentItemSchema();

            return $this->compareSchemas($existingSchema, $expectedSchema);
        } catch (\Exception $e) {
            throw WeaviateException::schemaValidationFailed($this->className, $e->getMessage());
        }
    }

    /**
     * Get the current schema for the ContentItem class.
     */
    public function getSchema(): ?array
    {
        try {
            if (!$this->schemaExists()) {
                return null;
            }

            return $this->client->schema()->get($this->className);
        } catch (\Exception $e) {
            throw WeaviateException::queryFailed('get_schema', $e->getMessage());
        }
    }

    /**
     * Build the ContentItem schema definition.
     */
    private function buildContentItemSchema(): array
    {
        return [
            'class' => $this->className,
            'description' => 'ContentItem from portable-content-php with nested blocks',
            'properties' => [
                [
                    'name' => 'contentId',
                    'dataType' => ['text'],
                    'description' => 'Unique identifier for the content item',
                ],
                [
                    'name' => 'type',
                    'dataType' => ['text'],
                    'description' => 'Type of the content item',
                ],
                [
                    'name' => 'title',
                    'dataType' => ['text'],
                    'description' => 'Title of the content item',
                ],
                [
                    'name' => 'summary',
                    'dataType' => ['text'],
                    'description' => 'Summary of the content item',
                ],
                [
                    'name' => 'createdAt',
                    'dataType' => ['date'],
                    'description' => 'Creation timestamp',
                ],
                [
                    'name' => 'updatedAt',
                    'dataType' => ['date'],
                    'description' => 'Last update timestamp',
                ],
                [
                    'name' => 'blockCount',
                    'dataType' => ['int'],
                    'description' => 'Number of blocks in the content item',
                ],
                [
                    'name' => 'blocks',
                    'dataType' => ['text'],
                    'description' => 'JSON-encoded array of blocks (markdown, etc.)',
                ],
            ],
        ];
    }

    /**
     * Compare two schemas to check if they match.
     */
    private function compareSchemas(array $existingSchema, array $expectedSchema): bool
    {
        // Check class name
        if ($existingSchema['class'] !== $expectedSchema['class']) {
            return false;
        }

        // Check properties
        $existingProps = $existingSchema['properties'] ?? [];
        $expectedProps = $expectedSchema['properties'] ?? [];

        if (count($existingProps) !== count($expectedProps)) {
            return false;
        }

        // Create lookup arrays for easier comparison
        $existingPropsMap = [];
        foreach ($existingProps as $prop) {
            $existingPropsMap[$prop['name']] = $prop;
        }

        foreach ($expectedProps as $expectedProp) {
            $name = $expectedProp['name'];
            
            if (!isset($existingPropsMap[$name])) {
                return false;
            }

            $existingProp = $existingPropsMap[$name];
            
            // Check data types
            if ($existingProp['dataType'] !== $expectedProp['dataType']) {
                return false;
            }
        }

        return true;
    }
}
