# Weaviate Repository Implementation Plan

## Overview

This document outlines the plan to implement a Weaviate-based repository for the `portable-content/weaviate-php-adapter` library, integrating with the existing `portable-content/portable-content-php` library architecture.

## Goals

1. **Extend Repository Pattern**: Implement `ContentRepositoryInterface` using Weaviate as the vector database backend
2. **Semantic Search**: Enable semantic search capabilities for content items and markdown blocks
3. **Maintain Compatibility**: Ensure full compatibility with existing portable-content-php patterns
4. **Vector Embeddings**: Automatically generate and store embeddings for content and blocks
5. **Hybrid Search**: Support both traditional filtering and semantic similarity search

## Architecture Overview

### Core Components

```
src/
├── Repository/
│   ├── WeaviateContentRepository.php     # Main repository implementation
│   └── WeaviateSchemaManager.php         # Schema management
└── Exception/
    └── WeaviateException.php             # Weaviate-specific exceptions
```

## Implementation Plan

### Phase 1: Foundation (Week 1)

#### 1.1 Exception Handling
- **File**: `src/Exception/WeaviateException.php`
- **Purpose**: Weaviate-specific exception handling
- **Features**:
  - Connection errors
  - Schema errors
  - Query errors
  - Data mapping errors

#### 1.2 Schema Management
- **File**: `src/Repository/WeaviateSchemaManager.php`
- **Purpose**: Manage Weaviate schema creation and updates
- **Features**:
  - Create ContentItem class schema (single collection)
  - Handle nested block objects within ContentItem (JSON-encoded)
  - Handle schema migrations
  - Validate schema compatibility
  - Dependency injection of WeaviateClient and class name

### Phase 2: Core Repository (Week 2)

#### 2.1 Weaviate Repository Implementation
- **File**: `src/Repository/WeaviateContentRepository.php`
- **Purpose**: Implement basic `ContentRepositoryInterface` methods using Weaviate
- **Methods**:
  ```php
  public function save(ContentItem $content): void
  public function findById(string $id): ?ContentItem
  public function findAll(int $limit = 20, int $offset = 0): array
  public function delete(string $id): void
  ```

#### 2.2 Data Mapping
- **Purpose**: Convert between domain objects and Weaviate objects
- **Features**:
  - ContentItem → Weaviate object mapping (with nested blocks)
  - MarkdownBlock → nested object mapping within ContentItem
  - Reverse mapping for retrieval (reconstruct ContentItem with blocks)
  - Handle block serialization/deserialization

### Phase 3: Full Interface Parity (Week 3)

#### 3.1 Additional Repository Methods
- **Purpose**: Implement remaining `ContentRepositoryInterface` methods for full SQLite parity
- **Methods**:
  ```php
  public function count(): int
  public function exists(string $id): bool
  public function findByType(string $type, int $limit = 20, int $offset = 0): array
  public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
  public function search(string $query, int $limit = 10): array
  public function findSimilar(ContentItem $content, int $limit = 10): array
  public function getCapabilities(): array
  public function supports(string $capability): bool
  ```

#### 3.2 Query Implementation
- **Purpose**: Implement Weaviate-specific queries for filtering and searching
- **Features**:
  - Count queries using Weaviate aggregation
  - Existence checks with efficient queries
  - Type-based filtering using where conditions
  - Date range queries with date property filters
  - Basic text search using Weaviate's text matching
  - Simple similarity based on content type (like SQLite implementation)
  - Capability reporting for Weaviate features

#### 3.3 Capability Management
- **Purpose**: Define and report Weaviate repository capabilities
- **Features**:
  - Capability constants and definitions
  - Dynamic capability detection based on Weaviate configuration
  - Compatibility reporting with SQLite repository

### Phase 3 Implementation Examples

#### Count and Exists Methods
```php
public function count(): int
{
    // Use Weaviate aggregation to count objects
    $result = $this->client->graphQL()->aggregate()
        ->withClassName('ContentItem')
        ->withFields('meta { count }')
        ->do();

    return $result['data']['Aggregate']['ContentItem'][0]['meta']['count'] ?? 0;
}

public function exists(string $id): bool
{
    // Efficient existence check using Get with minimal fields
    $result = $this->client->graphQL()->get()
        ->withClassName('ContentItem')
        ->withFields('contentId')
        ->withWhere(['path' => ['contentId'], 'operator' => 'Equal', 'valueText' => $id])
        ->withLimit(1)
        ->do();

    return !empty($result['data']['Get']['ContentItem']);
}
```

#### Filtering Methods
```php
public function findByType(string $type, int $limit = 20, int $offset = 0): array
{
    // Use where clause to filter by type
    $result = $this->client->graphQL()->get()
        ->withClassName('ContentItem')
        ->withFields($this->getContentItemFields())
        ->withWhere(['path' => ['type'], 'operator' => 'Equal', 'valueText' => $type])
        ->withLimit($limit)
        ->withOffset($offset)
        ->do();

    return $this->hydrateContentItems($result['data']['Get']['ContentItem'] ?? []);
}

public function search(string $query, int $limit = 10): array
{
    // Use Weaviate's text search capabilities
    $result = $this->client->graphQL()->get()
        ->withClassName('ContentItem')
        ->withFields($this->getContentItemFields())
        ->withWhere([
            'operator' => 'Or',
            'operands' => [
                ['path' => ['title'], 'operator' => 'Like', 'valueText' => "*{$query}*"],
                ['path' => ['summary'], 'operator' => 'Like', 'valueText' => "*{$query}*"]
            ]
        ])
        ->withLimit($limit)
        ->do();

    return $this->hydrateContentItems($result['data']['Get']['ContentItem'] ?? []);
}
```



## Weaviate Schema Design

The schema follows the aggregate root pattern from portable-content-php, where ContentItem contains its blocks as nested objects rather than separate collections.

### ContentItem Class (Single Collection)
```json
{
  "class": "ContentItem",
  "properties": [
    {"name": "contentId", "dataType": ["text"]},
    {"name": "type", "dataType": ["text"]},
    {"name": "title", "dataType": ["text"]},
    {"name": "summary", "dataType": ["text"]},
    {"name": "createdAt", "dataType": ["date"]},
    {"name": "updatedAt", "dataType": ["date"]},
    {"name": "blocks", "dataType": ["object[]"]},
    {"name": "blockCount", "dataType": ["int"]}
  ]
}
```

### Block Structure (Nested in ContentItem)
The `blocks` property contains an array of block objects with this structure:
```json
{
  "blockId": "uuid-string",
  "kind": "markdown",
  "source": "markdown content",
  "createdAt": "2024-01-01T00:00:00Z",
  "wordCount": 150
}
```

### Schema Benefits
- **Aggregate Consistency**: ContentItem and its blocks are stored together, maintaining transactional consistency
- **Simplified Queries**: No need for complex joins between collections
- **Performance**: Single read/write operations for complete content items
- **Domain Alignment**: Matches the immutable aggregate root pattern from portable-content-php

## Data Mapping Strategy

### ContentItem to Weaviate Object
```php
// Domain Object → Weaviate Object
$weaviateObject = [
    'contentId' => $contentItem->id,
    'type' => $contentItem->type,
    'title' => $contentItem->title,
    'summary' => $contentItem->summary,
    'createdAt' => $contentItem->createdAt->format(DateTimeInterface::RFC3339),
    'updatedAt' => $contentItem->updatedAt->format(DateTimeInterface::RFC3339),
    'blockCount' => count($contentItem->blocks),
    'blocks' => array_map(function($block) {
        return [
            'blockId' => $block->id,
            'kind' => 'markdown', // Block type
            'source' => $block->source,
            'createdAt' => $block->createdAt->format(DateTimeInterface::RFC3339),
            'wordCount' => $block->getWordCount()
        ];
    }, $contentItem->blocks)
];
```

### Weaviate Object to ContentItem
```php
// Weaviate Object → Domain Object
$blocks = array_map(function($blockData) {
    return MarkdownBlock::create($blockData['source'])
        ->withId($blockData['blockId'])
        ->withCreatedAt(new DateTimeImmutable($blockData['createdAt']));
}, $weaviateObject['blocks']);

$contentItem = ContentItem::create(
    $weaviateObject['type'],
    $weaviateObject['title'],
    $weaviateObject['summary'],
    $blocks
)->withId($weaviateObject['contentId'])
 ->withCreatedAt(new DateTimeImmutable($weaviateObject['createdAt']))
 ->withUpdatedAt(new DateTimeImmutable($weaviateObject['updatedAt']));
```

## Integration Points

### 1. Repository Factory Extension
```php
// In portable-content-php integration
RepositoryFactory::createWeaviateRepository(WeaviateConfig $config): ContentRepositoryInterface
```

### 2. Validation Integration
- Reuse existing `ContentValidationService`
- Maintain all validation rules
- Add Weaviate-specific validations if needed

### 3. Testing Strategy
- Unit tests for all components
- Integration tests with Weaviate testcontainer
- Performance benchmarks
- Compatibility tests with existing patterns

## Usage Example

```php
// Framework handles client initialization
$weaviateClient = WeaviateClient::connectToLocal('localhost:8080');

// Inject client and optional class name
$schemaManager = new WeaviateSchemaManager($weaviateClient, 'MyContentItem');
$repository = new WeaviateContentRepository($weaviateClient, 'MyContentItem');
```

## Usage Examples

### Basic Operations
```php
// Create and save content (same as existing pattern)
$block = MarkdownBlock::create('# AI and Vector Databases\n\nContent about AI...');
$content = ContentItem::create('article', 'AI Guide', 'Guide to AI', [$block]);
$repository->save($content);

// Retrieve content by ID
$retrieved = $repository->findById($content->id);

// List all content with pagination
$allContent = $repository->findAll(limit: 10, offset: 0);

// Delete content
$repository->delete($content->id);
```

## Testing Plan

### Unit Tests
- WeaviateConfig validation
- Schema manager operations
- Data mapping functionality
- Exception handling
- Query building for filtering methods
- Capability management

### Integration Tests
- Full CRUD operations (save, findById, findAll, delete)
- Additional methods (count, exists, findByType, findByDateRange)
- Search functionality (search, findSimilar)
- Schema management
- Weaviate client integration
- Error handling
- Capability reporting

### Performance Tests
- Large dataset operations
- Repository performance
- Query performance for filtering methods
- Memory usage

## Deployment Considerations

### Dependencies
- Weaviate server (Docker/cloud)
- PHP extensions (curl, json)

### Configuration
- Environment variables for sensitive data
- Schema versioning strategy
- Backup and recovery procedures

### Phase 2: Integration Testing (Week 2)

#### 2.1 Test Infrastructure Setup
- **Purpose**: Establish comprehensive integration testing framework
- **Components**:
  - Weaviate test container setup using Docker
  - Test database isolation and cleanup
  - Test data fixtures and factories
  - Integration test base classes

#### 2.2 Core CRUD Integration Tests
- **File**: `tests/Integration/Repository/WeaviateContentRepositoryTest.php`
- **Purpose**: Test all basic repository operations against real Weaviate instance
- **Test Cases**:
  ```php
  public function testSaveAndFindById(): void
  public function testSaveUpdatesExistingContent(): void
  public function testFindByIdReturnsNullForNonExistent(): void
  public function testFindAllWithPagination(): void
  public function testDeleteRemovesContent(): void
  public function testDeleteNonExistentContentDoesNotThrow(): void
  ```

#### 2.3 Schema Management Integration Tests
- **File**: `tests/Integration/Repository/WeaviateSchemaManagerTest.php`
- **Purpose**: Test schema creation, validation, and migration
- **Test Cases**:
  ```php
  public function testCreateSchemaCreatesCorrectStructure(): void
  public function testSchemaExistsDetectsExistingSchema(): void
  public function testValidateSchemaPassesForCorrectSchema(): void
  public function testValidateSchemaFailsForIncorrectSchema(): void
  public function testMigrateSchemaHandlesVersionChanges(): void
  ```

#### 2.4 Data Mapping Integration Tests
- **File**: `tests/Integration/Repository/DataMappingTest.php`
- **Purpose**: Test conversion between domain objects and Weaviate objects
- **Test Cases**:
  ```php
  public function testContentItemToWeaviateObjectMapping(): void
  public function testWeaviateObjectToContentItemMapping(): void
  public function testComplexBlockStructureMaintainsIntegrity(): void
  public function testDateTimeHandlingInMapping(): void
  public function testEmptyBlocksHandling(): void
  public function testLargeContentMapping(): void
  ```

#### 2.5 Error Handling Integration Tests
- **File**: `tests/Integration/Repository/ErrorHandlingTest.php`
- **Purpose**: Test error scenarios and exception handling
- **Test Cases**:
  ```php
  public function testConnectionErrorThrowsWeaviateException(): void
  public function testInvalidSchemaThrowsSchemaException(): void
  public function testMalformedDataThrowsMappingException(): void
  public function testWeaviateServerDownHandling(): void
  public function testTimeoutHandling(): void
  ```

#### 2.6 Performance Integration Tests
- **File**: `tests/Integration/Repository/PerformanceTest.php`
- **Purpose**: Test performance characteristics and benchmarks
- **Test Cases**:
  ```php
  public function testBulkInsertPerformance(): void
  public function testLargeDatasetRetrieval(): void
  public function testConcurrentOperations(): void
  public function testMemoryUsageUnderLoad(): void
  ```

#### 2.7 Test Configuration and Setup
- **Docker Compose**: Test environment with Weaviate container
- **Test Configuration**: Separate config for test environment
- **Fixtures**: Reusable test data and content items
- **Cleanup**: Automatic test data cleanup between tests

#### 2.8 Integration Test Examples

##### Basic CRUD Test Structure
```php
class WeaviateContentRepositoryTest extends WeaviateIntegrationTestCase
{
    private WeaviateContentRepository $repository;
    private WeaviateSchemaManager $schemaManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new WeaviateContentRepository($this->client, 'TestContentItem');
        $this->schemaManager = new WeaviateSchemaManager($this->client, 'TestContentItem');
        $this->schemaManager->createSchema();
    }

    public function testSaveAndFindById(): void
    {
        // Arrange
        $block = MarkdownBlock::create('# Test Content\n\nThis is test content.');
        $content = ContentItem::create('article', 'Test Article', 'Test summary', [$block]);

        // Act
        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals($content->id, $retrieved->id);
        $this->assertEquals($content->title, $retrieved->title);
        $this->assertEquals($content->type, $retrieved->type);
        $this->assertCount(1, $retrieved->blocks);
        $this->assertEquals($block->source, $retrieved->blocks[0]->source);
    }
}
```

##### Schema Management Test Structure
```php
class WeaviateSchemaManagerTest extends WeaviateIntegrationTestCase
{
    private WeaviateSchemaManager $schemaManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaManager = new WeaviateSchemaManager($this->client, 'TestContentItem');
    }

    public function testCreateSchemaCreatesCorrectStructure(): void
    {
        // Act
        $this->schemaManager->createSchema();

        // Assert
        $schema = $this->client->schema()->getter()->do();
        $contentItemClass = collect($schema['classes'])->firstWhere('class', 'TestContentItem');

        $this->assertNotNull($contentItemClass);
        $this->assertArrayHasKey('properties', $contentItemClass);

        $propertyNames = collect($contentItemClass['properties'])->pluck('name')->toArray();
        $expectedProperties = ['contentId', 'type', 'title', 'summary', 'createdAt', 'updatedAt', 'blocks', 'blockCount'];

        foreach ($expectedProperties as $property) {
            $this->assertContains($property, $propertyNames);
        }
    }
}
```

#### 2.9 Test Environment Configuration

##### Docker Compose for Testing
```yaml
# docker-compose.test.yml
version: '3.8'
services:
  weaviate-test:
    image: semitechnologies/weaviate:latest
    ports:
      - "8081:8080"
    environment:
      QUERY_DEFAULTS_LIMIT: 25
      AUTHENTICATION_ANONYMOUS_ACCESS_ENABLED: 'true'
      PERSISTENCE_DATA_PATH: '/var/lib/weaviate'
      DEFAULT_VECTORIZER_MODULE: 'none'
      CLUSTER_HOSTNAME: 'node1'
    volumes:
      - weaviate_test_data:/var/lib/weaviate

volumes:
  weaviate_test_data:
```

##### Base Integration Test Class
```php
abstract class WeaviateIntegrationTestCase extends TestCase
{
    protected WeaviateClient $client;
    protected string $testClassName = 'TestContentItem';

    protected function setUp(): void
    {
        parent::setUp();

        // Connect to test Weaviate instance
        $this->client = WeaviateClient::connectToLocal('localhost:8081');

        // Clean up any existing test schema
        $this->cleanupTestSchema();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestSchema();
        parent::tearDown();
    }

    private function cleanupTestSchema(): void
    {
        try {
            $this->client->schema()->classDeleter()
                ->withClassName($this->testClassName)
                ->do();
        } catch (Exception $e) {
            // Schema might not exist, ignore
        }
    }
}
```

## Timeline

- **Week 1**: Foundation (Config, Exceptions, Schema)
- **Week 2**: Integration Testing Framework and Core Tests
- **Week 3**: Core Repository Implementation (Basic CRUD)
- **Week 4**: Full Interface Parity (Additional Methods)
- **Week 5**: Testing and Documentation
- **Week 6**: Performance Optimization and Polish

## Success Criteria

1. ✅ **Full compatibility** with `ContentRepositoryInterface` (all 12 methods implemented)
2. ✅ **Complete parity** with SQLite repository functionality
3. ✅ **Reliable CRUD operations** with Weaviate backend
4. ✅ **Advanced querying** (count, exists, findByType, findByDateRange, search, findSimilar)
5. ✅ **Proper schema management** and data mapping
6. ✅ **Capability reporting** system matching SQLite repository
7. ✅ **90%+ test coverage** including all interface methods
8. ✅ **Performance benchmarks** meet requirements
9. ✅ **Comprehensive documentation** and examples
10. ✅ **Production-ready** error handling and logging

This implementation will provide a complete Weaviate-based repository backend with full parity to the SQLite repository while maintaining the clean, immutable design patterns of the portable-content-php library.
