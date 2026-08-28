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

    /**
     * @param FileDriver $fileDriver
     * @param FileNameParser $fileNameParser
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly FileNameParser $fileNameParser,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function scan(string $absoluteRoot): CategoryInterface
    {
        return $this->scanInto($absoluteRoot, '', '', self::ROOT_SORT_ORDER);
    }

    /**
     * Build one category from a directory, recursing into its sub-directories.
     *
     * @param string $absolutePath
     * @param string $relativePrefix
     * @param string $label
     * @param int $sortOrder
     * @return CategoryInterface
     */
    private function scanInto(
        string $absolutePath,
        string $relativePrefix,
        string $label,
        int $sortOrder
    ): CategoryInterface {
        $pages = [];
        $categories = [];

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
                    $parsed['sortOrder']
                );

                if (!$child->isEmpty()) {
                    $categories[] = $child;
                }

                continue;
            }

            if (!str_ends_with(strtolower($name), self::MARKDOWN_EXTENSION)) {
                continue;
            }

            $pages[] = new Page(
                $relativePrefix . $name,
                $name,
                $parsed['label'],
                $parsed['sortOrder'],
                $parsed['isIndex']
            );
        }

        return new Category($label, $sortOrder, $this->sortPages($pages), $this->sortCategories($categories));
    }

    /**
     * Index pages first, then by numeric prefix, then alphabetically.
     *
     * @param PageInterface[] $pages
     * @return PageInterface[]
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
     * @param CategoryInterface[] $categories
     * @return CategoryInterface[]
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
