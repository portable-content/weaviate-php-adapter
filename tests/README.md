# Weaviate PHP Adapter Tests

This directory contains comprehensive tests for the Weaviate PHP Adapter.

## Test Structure

```
tests/
├── Unit/                           # Unit tests (no external dependencies)
│   ├── Config/
│   │   └── WeaviateConfigTest.php
│   ├── Exception/
│   │   └── WeaviateExceptionTest.php
│   └── Repository/
│       └── WeaviateSchemaManagerTest.php
├── Integration/                    # Integration tests (require Weaviate)
│   ├── Config/
│   │   └── WeaviateConfigIntegrationTest.php
│   └── Repository/
│       └── WeaviateSchemaManagerIntegrationTest.php
├── Support/
│   └── WeaviateTestHelper.php     # Test utilities
└── README.md                      # This file
```

## Running Tests

### Prerequisites

1. **PHP 8.3+** with required extensions
2. **Composer** dependencies installed
3. **Weaviate server** running (for integration tests only)

### Unit Tests Only

Unit tests don't require a Weaviate server and can be run independently:

```bash
# Run all unit tests
composer test:unit

# Run specific unit test class
./vendor/bin/phpunit tests/Unit/Config/WeaviateConfigTest.php

# Run unit tests with coverage
./vendor/bin/phpunit --testsuite=Unit --coverage-html coverage/unit
```

### Integration Tests

Integration tests require a running Weaviate instance.

#### Option 1: Docker (Recommended)

Start Weaviate using Docker:

```bash
# Start test Weaviate instance
docker-compose -f docker-compose.test.yml up -d

# Wait for Weaviate to be ready
curl -f http://localhost:8082/v1/.well-known/ready

# Run integration tests
composer test:integration

# Stop test Weaviate instance
docker-compose -f docker-compose.test.yml down
```

#### Option 2: Local Weaviate Instance

If you have a local Weaviate instance running:

```bash
# Start Weaviate with Docker
docker run -d \
  --name weaviate-test \
  -p 8080:8080 \
  -e QUERY_DEFAULTS_LIMIT=25 \
  -e AUTHENTICATION_ANONYMOUS_ACCESS_ENABLED=true \
  -e PERSISTENCE_DATA_PATH='/var/lib/weaviate' \
  -e DEFAULT_VECTORIZER_MODULE='none' \
  -e ENABLE_MODULES='' \
  -e CLUSTER_HOSTNAME='node1' \
  semitechnologies/weaviate:latest

# Wait for Weaviate to be ready
curl http://localhost:8080/v1/.well-known/ready

# Run integration tests
composer test:integration

# Stop and remove container when done
docker stop weaviate-test
docker rm weaviate-test
```

#### Option 2: Docker Compose

Create a `docker-compose.test.yml` file:

```yaml
version: '3.4'
services:
  weaviate:
    image: semitechnologies/weaviate:latest
    ports:
      - "8080:8080"
    environment:
      QUERY_DEFAULTS_LIMIT: 25
      AUTHENTICATION_ANONYMOUS_ACCESS_ENABLED: 'true'
      PERSISTENCE_DATA_PATH: '/var/lib/weaviate'
      DEFAULT_VECTORIZER_MODULE: 'none'
      ENABLE_MODULES: ''
      CLUSTER_HOSTNAME: 'node1'
```

```bash
# Start Weaviate
docker-compose -f docker-compose.test.yml up -d

# Run integration tests
composer test:integration

# Stop Weaviate
docker-compose -f docker-compose.test.yml down
```

### All Tests

Run both unit and integration tests:

```bash
# Run all tests (requires Weaviate server)
composer test

# Run with coverage
composer test:coverage
```

## Environment Configuration

Integration tests can be configured using environment variables:

```bash
# Weaviate connection settings
export WEAVIATE_TEST_HOST=localhost
export WEAVIATE_TEST_PORT=8080
export WEAVIATE_TEST_SCHEME=http

# Run tests with custom configuration
composer test:integration
```

## Test Configuration

### WeaviateTestHelper

The `WeaviateTestHelper` class provides utilities for integration tests:

- **Automatic Weaviate detection**: Tests are skipped if Weaviate is unavailable
- **Unique schema prefixes**: Each test run uses a unique schema prefix to avoid conflicts
- **Automatic cleanup**: Schemas are cleaned up after each test
- **Connection management**: Reuses client connections for better performance

### Test Isolation

- **Unit tests**: Completely isolated, use mocks for external dependencies
- **Integration tests**: Use unique schema prefixes to avoid conflicts
- **Cleanup**: Automatic schema cleanup after each test
- **Skipping**: Integration tests are automatically skipped if Weaviate is unavailable

## Debugging Tests

### Verbose Output

```bash
# Run tests with verbose output
./vendor/bin/phpunit --verbose

# Run specific test with debug info
./vendor/bin/phpunit --verbose tests/Integration/Repository/WeaviateSchemaManagerIntegrationTest.php
```

### Test Failures

If integration tests fail:

1. **Check Weaviate status**:
   ```bash
   curl http://localhost:8080/v1/.well-known/ready
   curl http://localhost:8080/v1/.well-known/live
   ```

2. **Check Weaviate logs**:
   ```bash
   docker logs weaviate-test
   ```

3. **Manual cleanup**:
   ```bash
   # List all schemas
   curl http://localhost:8080/v1/schema
   
   # Delete specific schema (replace ClassName)
   curl -X DELETE http://localhost:8080/v1/schema/ClassName
   ```

## Coverage Reports

Generate coverage reports:

```bash
# HTML coverage report
composer test:coverage

# View coverage report
open coverage/index.html
```

## Continuous Integration

For CI environments, use the provided GitHub Actions workflow or adapt for your CI system:

```yaml
# Example CI configuration
- name: Start Weaviate
  run: |
    docker run -d --name weaviate-test -p 8080:8080 \
      -e AUTHENTICATION_ANONYMOUS_ACCESS_ENABLED=true \
      -e DEFAULT_VECTORIZER_MODULE=none \
      semitechnologies/weaviate:latest

- name: Wait for Weaviate
  run: |
    timeout 60 bash -c 'until curl -f http://localhost:8080/v1/.well-known/ready; do sleep 2; done'

- name: Run tests
  run: composer test
```

## Performance Testing

For performance testing of the Weaviate adapter:

```bash
# Run tests with timing information
./vendor/bin/phpunit --verbose --log-junit results.xml

# Profile memory usage
php -d memory_limit=256M ./vendor/bin/phpunit --verbose
```

## Troubleshooting

### Common Issues

1. **"Weaviate is not available"**: Ensure Weaviate server is running and accessible
2. **Schema conflicts**: Tests use unique prefixes, but manual cleanup may be needed
3. **Timeout errors**: Increase timeout in test configuration
4. **Memory issues**: Increase PHP memory limit for large test suites

### Getting Help

- Check Weaviate documentation: https://weaviate.io/developers/weaviate
- Review test logs for detailed error messages
- Ensure all dependencies are properly installed
