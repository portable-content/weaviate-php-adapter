<?php

declare(strict_types=1);

namespace PortableContent\Tests\Support;

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;

/**
 * Factory for creating test data objects.
 */
final class TestDataFactory
{
    /**
     * Create a simple MarkdownBlock for testing.
     */
    public static function createMarkdownBlock(
        ?string $source = null,
        ?string $id = null,
        ?\DateTimeImmutable $createdAt = null
    ): MarkdownBlock {
        $source ??= "# Test Content\n\nThis is test markdown content for testing purposes.";

        // Create block normally first
        $block = MarkdownBlock::create($source);

        // Now we can use setter methods to customize properties if needed
        if ($id !== null) {
            $block->setId($id);
        }

        if ($createdAt !== null) {
            $block->setCreatedAt($createdAt);
        }

        return $block;
    }

    /**
     * Create a ContentItem for testing.
     *
     * @param MarkdownBlock[]|null $blocks
     */
    public static function createContentItem(
        ?string $type = null,
        ?string $title = null,
        ?string $summary = null,
        ?array $blocks = null,
        ?string $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ): ContentItem {
        $type ??= 'article';
        $title ??= 'Test Article';
        $summary ??= 'This is a test article summary';
        $blocks ??= [self::createMarkdownBlock()];

        // Create ContentItem normally first
        $contentItem = ContentItem::create($type, $title, $summary, $blocks);

        // For immutable properties (id, createdAt, updatedAt), we still need reflection
        // since these don't have setter methods (by design)
        if ($id !== null || $createdAt !== null || $updatedAt !== null) {
            $reflection = new \ReflectionClass($contentItem);

            if ($id !== null) {
                $idProperty = $reflection->getProperty('id');
                $idProperty->setAccessible(true);
                $idProperty->setValue($contentItem, $id);
            }

            if ($createdAt !== null) {
                $createdAtProperty = $reflection->getProperty('createdAt');
                $createdAtProperty->setAccessible(true);
                $createdAtProperty->setValue($contentItem, $createdAt);
            }

            if ($updatedAt !== null) {
                $updatedAtProperty = $reflection->getProperty('updatedAt');
                $updatedAtProperty->setAccessible(true);
                $updatedAtProperty->setValue($contentItem, $updatedAt);
            }
        }

        return $contentItem;
    }

    /**
     * Create multiple ContentItems for testing.
     *
     * @return ContentItem[]
     */
    public static function createMultipleContentItems(int $count = 3): array
    {
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $items[] = self::createContentItem(
                type: "type{$i}",
                title: "Test Article {$i}",
                summary: "Summary for test article {$i}",
                blocks: [
                    self::createMarkdownBlock("# Article {$i}\n\nContent for article {$i}"),
                ]
            );
        }

        return $items;
    }

    /**
     * Create a ContentItem with multiple blocks.
     */
    public static function createContentItemWithMultipleBlocks(int $blockCount = 3): ContentItem
    {
        $blocks = [];

        for ($i = 1; $i <= $blockCount; $i++) {
            $blocks[] = self::createMarkdownBlock(
                "# Block {$i}\n\nThis is block {$i} content with some text to test."
            );
        }

        return self::createContentItem(
            type: 'multi-block-article',
            title: 'Article with Multiple Blocks',
            summary: 'An article containing multiple markdown blocks',
            blocks: $blocks
        );
    }

    /**
     * Create a ContentItem with specific dates for testing date ranges.
     */
    public static function createContentItemWithDate(
        \DateTimeImmutable $date,
        ?string $title = null
    ): ContentItem {
        return self::createContentItem(
            title: $title ?? "Article from {$date->format('Y-m-d')}",
            createdAt: $date,
            updatedAt: $date
        );
    }

    /**
     * Create ContentItems with different types for testing.
     *
     * @return ContentItem[]
     */
    public static function createContentItemsWithDifferentTypes(): array
    {
        return [
            self::createContentItem(type: 'article', title: 'Test Article'),
            self::createContentItem(type: 'blog-post', title: 'Test Blog Post'),
            self::createContentItem(type: 'documentation', title: 'Test Documentation'),
            self::createContentItem(type: 'tutorial', title: 'Test Tutorial'),
        ];
    }

    /**
     * Create a large ContentItem for performance testing.
     */
    public static function createLargeContentItem(): ContentItem
    {
        $largeContent = str_repeat("This is a large block of content. ", 1000);
        $block = self::createMarkdownBlock("# Large Content\n\n{$largeContent}");

        return self::createContentItem(
            type: 'large-article',
            title: 'Large Test Article',
            summary: 'A large article for performance testing',
            blocks: [$block]
        );
    }

    /**
     * Create ContentItems for date range testing.
     *
     * @return ContentItem[]
     */
    public static function createContentItemsForDateRange(): array
    {
        $baseDate = new \DateTimeImmutable('2024-01-01');

        return [
            self::createContentItemWithDate($baseDate, 'January Article'),
            self::createContentItemWithDate($baseDate->modify('+1 month'), 'February Article'),
            self::createContentItemWithDate($baseDate->modify('+2 months'), 'March Article'),
            self::createContentItemWithDate($baseDate->modify('+6 months'), 'July Article'),
        ];
    }
}
