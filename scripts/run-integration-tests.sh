#!/bin/bash

# Script to run integration tests with proper Weaviate setup
# This script handles starting/stopping Weaviate and running tests

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.test.yml"
WEAVIATE_URL="http://localhost:8082"
MAX_WAIT_TIME=60

echo -e "${GREEN}🚀 Starting Weaviate PHP Adapter Integration Tests${NC}"

# Function to check if Weaviate is ready
check_weaviate_ready() {
    local url="$1/v1/.well-known/ready"
    curl -f -s "$url" > /dev/null 2>&1
}

# Function to wait for Weaviate to be ready
wait_for_weaviate() {
    echo -e "${YELLOW}⏳ Waiting for Weaviate to be ready...${NC}"
    local count=0
    while ! check_weaviate_ready "$WEAVIATE_URL"; do
        if [ $count -ge $MAX_WAIT_TIME ]; then
            echo -e "${RED}❌ Weaviate failed to start within $MAX_WAIT_TIME seconds${NC}"
            return 1
        fi
        sleep 2
        count=$((count + 2))
        echo -n "."
    done
    echo -e "\n${GREEN}✅ Weaviate is ready!${NC}"
}

# Function to cleanup
cleanup() {
    echo -e "${YELLOW}🧹 Cleaning up...${NC}"
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" down -v > /dev/null 2>&1 || true
}

# Set trap to cleanup on exit
trap cleanup EXIT

# Check if Docker is available
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker is not installed or not in PATH${NC}"
    exit 1
fi

# Check if Docker Compose is available (try both old and new syntax)
if ! docker compose version &> /dev/null && ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}❌ Docker Compose is not available${NC}"
    exit 1
fi

# Use the appropriate docker compose command
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

# Check if compose file exists
if [ ! -f "$COMPOSE_FILE" ]; then
    echo -e "${RED}❌ Docker Compose file '$COMPOSE_FILE' not found${NC}"
    exit 1
fi

# Start Weaviate
echo -e "${YELLOW}🐳 Starting Weaviate test container...${NC}"
$DOCKER_COMPOSE -f "$COMPOSE_FILE" up -d

# Wait for Weaviate to be ready
if ! wait_for_weaviate; then
    echo -e "${RED}❌ Failed to start Weaviate${NC}"
    exit 1
fi

# Run the tests
echo -e "${GREEN}🧪 Running integration tests...${NC}"

# Set environment variables for tests
export WEAVIATE_TEST_HOST=localhost
export WEAVIATE_TEST_PORT=8082
export WEAVIATE_TEST_SCHEME=http

# Run different test suites based on arguments
case "${1:-all}" in
    "unit")
        echo -e "${YELLOW}Running unit tests only...${NC}"
        ./vendor/bin/phpunit --testsuite=Unit
        ;;
    "integration")
        echo -e "${YELLOW}Running integration tests only...${NC}"
        ./vendor/bin/phpunit --testsuite=Integration
        ;;
    "schema")
        echo -e "${YELLOW}Running schema management tests...${NC}"
        ./vendor/bin/phpunit tests/Integration/Repository/WeaviateSchemaManagerIntegrationTest.php
        ;;
    "crud")
        echo -e "${YELLOW}Running CRUD tests...${NC}"
        ./vendor/bin/phpunit tests/Integration/Repository/WeaviateContentRepositoryTest.php
        ;;
    "mapping")
        echo -e "${YELLOW}Running data mapping tests...${NC}"
        ./vendor/bin/phpunit tests/Integration/Repository/DataMappingIntegrationTest.php
        ;;
    "errors")
        echo -e "${YELLOW}Running error handling tests...${NC}"
        ./vendor/bin/phpunit tests/Integration/Repository/ErrorHandlingIntegrationTest.php
        ;;
    "performance")
        echo -e "${YELLOW}Running performance tests...${NC}"
        ./vendor/bin/phpunit tests/Integration/Repository/PerformanceIntegrationTest.php
        ;;
    "coverage")
        echo -e "${YELLOW}Running all tests with coverage...${NC}"
        ./vendor/bin/phpunit --coverage-html coverage --coverage-clover coverage.xml
        ;;
    "all"|*)
        echo -e "${YELLOW}Running all tests...${NC}"
        ./vendor/bin/phpunit
        ;;
esac

# Check test results
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
else
    echo -e "${RED}❌ Some tests failed${NC}"
    exit 1
fi

echo -e "${GREEN}🎉 Integration tests completed successfully!${NC}"
