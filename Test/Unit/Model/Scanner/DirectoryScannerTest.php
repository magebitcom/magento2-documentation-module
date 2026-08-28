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
use Magebit\Documentation\Model\Scanner\FrontMatterReader;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Parser;

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
     * @var string
     */
    private string $deepRoot;

    /**
     * @var string
     */
    private string $frontMatterRoot;

    /**
     * @var string
     */
    private string $brokenFrontMatterRoot;

    /**
     * @var DirectoryScanner
     */
    private DirectoryScanner $scanner;

    protected function setUp(): void
    {
        $base = (string)realpath(sys_get_temp_dir());
        $this->root = $base . '/magedoc-scan-' . uniqid('', true);
        $this->orderingRoot = $this->root . '-ordering';
        $this->deepRoot = $this->root . '-deep';
        $this->frontMatterRoot = $this->root . '-front-matter';
        $this->brokenFrontMatterRoot = $this->root . '-broken-front-matter';

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

        // Twelve levels, two more than the scanner will follow.
        $nested = $this->deepRoot;
        for ($level = 1; $level <= 12; $level++) {
            $nested .= '/level';
            mkdir($nested, 0777, true);
            file_put_contents($nested . '/page.md', '# Level ' . $level);
        }

        // Front matter titles reverse the alphabetical order of the file names they override.
        mkdir($this->frontMatterRoot, 0777, true);
        file_put_contents($this->frontMatterRoot . '/index.md', "---\norder: 999\n---\n# Overview");
        file_put_contents($this->frontMatterRoot . '/1-alpha.md', "---\ntitle: Zeta\n---\n# Zeta");
        file_put_contents($this->frontMatterRoot . '/1-zeta.md', "---\ntitle: Alpha\n---\n# Alpha");
        file_put_contents($this->frontMatterRoot . '/9-later.md', "---\norder: 2\n---\n# Later");
        file_put_contents($this->frontMatterRoot . '/3-middle.md', '# Middle');

        mkdir($this->brokenFrontMatterRoot, 0777, true);
        file_put_contents($this->brokenFrontMatterRoot . '/2-broken.md', "---\ntitle: \"unclosed\n---\n# Broken");

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->scanner = new DirectoryScanner(
            new File(),
            new FileNameParser(),
            new FrontMatterReader(new File(), new Parser(), $logger),
            $logger
        );
    }

    protected function tearDown(): void
    {
        $driver = new File();
        $driver->deleteDirectory($this->root);
        $driver->deleteDirectory($this->orderingRoot);
        $driver->deleteDirectory($this->deepRoot);
        $driver->deleteDirectory($this->frontMatterRoot);
        $driver->deleteDirectory($this->brokenFrontMatterRoot);
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

        $this->assertSame(['index.md', '1-intro.md', '2-setup.md'], $names);
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

        $this->assertSame(['Basics', 'Advanced'], $labels);
        $this->assertNotContains('Empty', $labels);
    }

    public function testNestedPagesCarryTheirPathRelativeToTheRoot(): void
    {
        $categories = $this->scanner->scan($this->root)->getCategories();

        $this->assertSame('1-basics/a.md', $categories[0]->getPages()[0]->getRelativePath());
    }

    public function testMissingDirectoryYieldsAnEmptyRootAndLogs(): void
    {
        $missing = $this->root . '/does-not-exist';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('could not read a documentation directory'),
                $this->callback(
                    static fn (mixed $context): bool => is_array($context)
                        && ($context['path'] ?? null) === $missing
                )
            );

        $scanner = new DirectoryScanner(
            new File(),
            new FileNameParser(),
            new FrontMatterReader(new File(), new Parser(), $logger),
            $logger
        );

        $this->assertTrue($scanner->scan($missing)->isEmpty());
    }

    public function testStopsDescendingBeyondTheDepthLimitAndLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('nests too deeply'), $this->arrayHasKey('path'));

        $scanner = new DirectoryScanner(
            new File(),
            new FileNameParser(),
            new FrontMatterReader(new File(), new Parser(), $logger),
            $logger
        );

        $category = $scanner->scan($this->deepRoot);
        $depth = 0;
        while ($category->getCategories() !== []) {
            $category = $category->getCategories()[0];
            $depth++;
        }

        $this->assertSame(10, $depth);
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

    public function testFrontMatterTitleOverridesTheFileNameLabel(): void
    {
        $titles = array_map(
            static fn (PageInterface $page): string => $page->getTitle(),
            $this->scanner->scan($this->frontMatterRoot)->getPages()
        );

        $this->assertSame(['Overview', 'Alpha', 'Zeta', 'Later', 'Middle'], $titles);
    }

    public function testFrontMatterOrderOverridesTheNumericPrefix(): void
    {
        $orders = array_map(
            static fn (PageInterface $page): int => $page->getSortOrder(),
            $this->scanner->scan($this->frontMatterRoot)->getPages()
        );

        $this->assertSame([999, 1, 1, 2, 3], $orders);
    }

    public function testIndexStaysFirstAndTiesBreakOnTheFinalTitle(): void
    {
        $names = array_map(
            static fn (PageInterface $page): string => $page->getFileName(),
            $this->scanner->scan($this->frontMatterRoot)->getPages()
        );

        $this->assertSame(['index.md', '1-zeta.md', '1-alpha.md', '9-later.md', '3-middle.md'], $names);
    }

    public function testMalformedFrontMatterFallsBackToTheFileNameConvention(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('could not parse the front matter'), $this->arrayHasKey('path'));

        $scanner = new DirectoryScanner(
            new File(),
            new FileNameParser(),
            new FrontMatterReader(new File(), new Parser(), $logger),
            $this->createMock(LoggerInterface::class)
        );

        $page = $scanner->scan($this->brokenFrontMatterRoot)->getPages()[0];

        $this->assertSame('Broken', $page->getTitle());
        $this->assertSame(2, $page->getSortOrder());
    }
}
