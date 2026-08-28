<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Scanner;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\PageInterface;
use Magebit\Documentation\Api\DirectoryScannerInterface;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\Page;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Psr\Log\LoggerInterface;

class DirectoryScanner implements DirectoryScannerInterface
{
    private const MARKDOWN_EXTENSION = '.md';

    private const ROOT_SORT_ORDER = 100;

    private const MAX_DEPTH = 10;

    /**
     * @param FileDriver $fileDriver
     * @param FileNameParser $fileNameParser
     * @param FrontMatterReader $frontMatterReader
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly FileNameParser $fileNameParser,
        private readonly FrontMatterReader $frontMatterReader,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function scan(string $absoluteRoot): CategoryInterface
    {
        return $this->scanInto($absoluteRoot, '', '', self::ROOT_SORT_ORDER, 0);
    }

    /**
     * Build one category from a directory, recursing into its sub-directories.
     *
     * @param string $absolutePath
     * @param string $relativePrefix
     * @param string $label
     * @param int $sortOrder
     * @param int $depth
     * @return CategoryInterface
     */
    private function scanInto(
        string $absolutePath,
        string $relativePrefix,
        string $label,
        int $sortOrder,
        int $depth
    ): CategoryInterface {
        $pages = [];
        $categories = [];

        // Stops symlink loops, which the file driver follows, from building an endless tree.
        if ($depth > self::MAX_DEPTH) {
            $this->logger->warning(
                'Magebit_Documentation stopped reading a documentation directory that nests too deeply.',
                ['path' => $absolutePath, 'maxDepth' => self::MAX_DEPTH]
            );

            return new Category($label, $sortOrder, [], []);
        }

        try {
            $entries = $this->fileDriver->readDirectory($absolutePath);
        } catch (FileSystemException $e) {
            $this->logger->warning(
                'Magebit_Documentation could not read a documentation directory.',
                ['path' => $absolutePath, 'exception' => $e->getMessage()]
            );

            return new Category($label, $sortOrder, [], []);
        }

        foreach ($entries as $entry) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $name = basename($entry);
            $parsed = $this->fileNameParser->parse($name);

            if ($this->fileDriver->isDirectory($entry)) {
                $child = $this->scanInto(
                    $entry,
                    $relativePrefix . $name . '/',
                    $parsed['label'],
                    $parsed['sortOrder'],
                    $depth + 1
                );

                if (!$child->isEmpty()) {
                    $categories[] = $child;
                }

                continue;
            }

            if (!str_ends_with(strtolower($name), self::MARKDOWN_EXTENSION)) {
                continue;
            }

            $frontMatter = $this->frontMatterReader->read($entry);

            $pages[] = new Page(
                $relativePrefix . $name,
                $name,
                $this->title($frontMatter, $parsed['label']),
                $this->sortOrder($frontMatter, $parsed['sortOrder']),
                $parsed['isIndex']
            );
        }

        return new Category($label, $sortOrder, $this->sortPages($pages), $this->sortCategories($categories));
    }

    /**
     * The front matter title, or the file name label when there is no usable one.
     *
     * @param array<string,mixed> $frontMatter
     * @param string $fallback
     * @return string
     */
    private function title(array $frontMatter, string $fallback): string
    {
        $title = $frontMatter['title'] ?? null;

        return is_string($title) && trim($title) !== '' ? trim($title) : $fallback;
    }

    /**
     * The front matter order, or the numeric file name prefix when there is no usable one.
     *
     * @param array<string,mixed> $frontMatter
     * @param int $fallback
     * @return int
     */
    private function sortOrder(array $frontMatter, int $fallback): int
    {
        $sortOrder = $frontMatter['order'] ?? null;

        return is_int($sortOrder) ? $sortOrder : $fallback;
    }

    /**
     * Index pages first, then by numeric prefix, then alphabetically.
     *
     * @param list<PageInterface> $pages
     * @return list<PageInterface>
     */
    private function sortPages(array $pages): array
    {
        usort($pages, static function (PageInterface $a, PageInterface $b): int {
            return [$a->isIndex() ? 0 : 1, $a->getSortOrder()]
                <=> [$b->isIndex() ? 0 : 1, $b->getSortOrder()]
                ?: strcasecmp($a->getTitle(), $b->getTitle());
        });

        return $pages;
    }

    /**
     * Order categories by numeric prefix, then alphabetically.
     *
     * @param list<CategoryInterface> $categories
     * @return list<CategoryInterface>
     */
    private function sortCategories(array $categories): array
    {
        usort($categories, static function (CategoryInterface $a, CategoryInterface $b): int {
            return $a->getSortOrder() <=> $b->getSortOrder()
                ?: strcasecmp($a->getLabel(), $b->getLabel());
        });

        return $categories;
    }
}
