<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function tearDown(): void
    {
        // Reset any static state
        parent::tearDown();
    }
}
