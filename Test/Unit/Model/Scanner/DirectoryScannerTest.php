<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Scanner;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\PageInterface;
use Magebit\Documentation\Model\Scanner\DirectoryScanner;
use Magebit\Documentation\Model\Scanner\FileNameParser;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DirectoryScannerTest extends TestCase
{
    /**
     * @var string
     */
    private string $root;

    /**
     * @var string
     */
    private string $orderingRoot;

    /**
     * @var DirectoryScanner
     */
    private DirectoryScanner $scanner;

    protected function setUp(): void
    {
        $base = (string)realpath(sys_get_temp_dir());
        $this->root = $base . '/magedoc-scan-' . uniqid('', true);
        $this->orderingRoot = $this->root . '-ordering';

        mkdir($this->root . '/2-advanced', 0777, true);
        mkdir($this->root . '/1-basics', 0777, true);
        mkdir($this->root . '/empty', 0777, true);
        file_put_contents($this->root . '/2-setup.md', '# Setup');
        file_put_contents($this->root . '/index.md', '# Overview');
        file_put_contents($this->root . '/1-intro.md', '# Intro');
        file_put_contents($this->root . '/notes.txt', 'ignored');
        file_put_contents($this->root . '/1-basics/a.md', '# A');
        file_put_contents($this->root . '/2-advanced/b.md', '# B');

        // Names chosen so the on-disk order differs from every expected order.
        mkdir($this->orderingRoot . '/10-beta', 0777, true);
        mkdir($this->orderingRoot . '/3-Zulu', 0777, true);
        mkdir($this->orderingRoot . '/3-banana', 0777, true);
        file_put_contents($this->orderingRoot . '/10-beta/page.md', '# Beta');
        file_put_contents($this->orderingRoot . '/3-Zulu/page.md', '# Zulu');
        file_put_contents($this->orderingRoot . '/3-banana/page.md', '# Banana');
        file_put_contents($this->orderingRoot . '/1-Zulu.md', '# Zulu');
        file_put_contents($this->orderingRoot . '/1-alpha.md', '# Alpha');
        file_put_contents($this->orderingRoot . '/Zebra.md', '# Zebra');
        file_put_contents($this->orderingRoot . '/banana.md', '# Banana');

        $this->scanner = new DirectoryScanner(
            new File(),
            new FileNameParser(),
            $this->createMock(LoggerInterface::class)
        );
    }

    protected function tearDown(): void
    {
        $driver = new File();
        $driver->deleteDirectory($this->root);
        $driver->deleteDirectory($this->orderingRoot);
    }

    public function testOrdersIndexFirstThenByNumericPrefix(): void
    {
        $pages = $this->scanner->scan($this->root)->getPages();

        $this->assertSame(
            ['index.md', '1-intro.md', '2-setup.md'],
            array_map(static fn (PageInterface $page): string => $page->getFileName(), $pages)
        );
    }

    public function testIgnoresNonMarkdownFiles(): void
    {
        $names = array_map(
            static fn (PageInterface $page): string => $page->getFileName(),
            $this->scanner->scan($this->root)->getPages()
        );

        $this->assertNotContains('notes.txt', $names);
    }

    public function testOrdersCategoriesByNumericPrefix(): void
    {
        $labels = array_map(
            static fn (CategoryInterface $category): string => $category->getLabel(),
            $this->scanner->scan($this->root)->getCategories()
        );

        $this->assertSame(['Basics', 'Advanced'], $labels);
    }

    public function testDropsEmptyCategories(): void
    {
        $labels = array_map(
            static fn (CategoryInterface $category): string => $category->getLabel(),
            $this->scanner->scan($this->root)->getCategories()
        );

        $this->assertNotContains('Empty', $labels);
    }

    public function testNestedPagesCarryTheirPathRelativeToTheRoot(): void
    {
        $categories = $this->scanner->scan($this->root)->getCategories();

        $this->assertSame('1-basics/a.md', $categories[0]->getPages()[0]->getRelativePath());
    }

    public function testMissingDirectoryYieldsAnEmptyRootAndLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $scanner = new DirectoryScanner(new File(), new FileNameParser(), $logger);

        $this->assertTrue($scanner->scan($this->root . '/does-not-exist')->isEmpty());
    }

    public function testOrdersPagesByPrefixThenCaseInsensitiveLabel(): void
    {
        $names = array_map(
            static fn (PageInterface $page): string => $page->getFileName(),
            $this->scanner->scan($this->orderingRoot)->getPages()
        );

        $this->assertSame(['1-alpha.md', '1-Zulu.md', 'banana.md', 'Zebra.md'], $names);
    }

    public function testOrdersCategoriesByPrefixThenCaseInsensitiveLabel(): void
    {
        $labels = array_map(
            static fn (CategoryInterface $category): string => $category->getLabel(),
            $this->scanner->scan($this->orderingRoot)->getCategories()
        );

        $this->assertSame(['Banana', 'Zulu', 'Beta'], $labels);
    }
}
