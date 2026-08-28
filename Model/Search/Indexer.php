<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Search;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\PageInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\PageRepositoryInterface;
use Magebit\Documentation\Model\Tree\Builder;
use Psr\Log\LoggerInterface;

/**
 * Reads every documentation page and turns it into searchable text.
 *
 * Built from the unfiltered tree on purpose, so one index serves every admin role.
 *
 * @phpstan-type SearchRecord array{
 *     module: string,
 *     moduleTitle: string,
 *     section: string,
 *     path: string,
 *     title: string,
 *     headings: string,
 *     body: string
 * }
 */
class Indexer
{
    /**
     * Markdown that carries no searchable words, in the order it has to be removed.
     */
    private const NOISE_PATTERNS = [
        '/```.*?```/s' => ' ',
        '/~~~.*?~~~/s' => ' ',
        '/`[^`]*`/' => ' ',
        '/!\[[^\]]*\]\([^)]*\)/' => ' ',
        '/\[([^\]]*)\]\([^)]*\)/' => '$1',
        '~<?\bhttps?://[^\s>]*>?~i' => ' ',
    ];

    /**
     * @param Builder $builder
     * @param PageRepositoryInterface $pageRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * One record per documentation page, in the order the tree holds them.
     *
     * @return list<SearchRecord>
     */
    public function build(): array
    {
        $records = [];

        foreach ($this->builder->build() as $module) {
            foreach ($module->getSections() as $section) {
                $this->collect($module, $section, $section->getRoot(), $records);
            }
        }

        $this->logger->info(
            'Magebit_Documentation built the documentation search index.',
            ['pages' => count($records)]
        );

        return $records;
    }

    /**
     * Add one record per page of a category, then of everything below it.
     *
     * @param ModuleDocsInterface $module
     * @param SectionInterface $section
     * @param CategoryInterface $category
     * @param list<SearchRecord> $records
     * @return void
     */
    private function collect(
        ModuleDocsInterface $module,
        SectionInterface $section,
        CategoryInterface $category,
        array &$records
    ): void {
        foreach ($category->getPages() as $page) {
            $records[] = $this->record($module, $section, $page);
        }

        foreach ($category->getCategories() as $child) {
            $this->collect($module, $section, $child, $records);
        }
    }

    /**
     * Read one page and split it into its title, its headings and its body text.
     *
     * @param ModuleDocsInterface $module
     * @param SectionInterface $section
     * @param PageInterface $page
     * @return SearchRecord
     */
    private function record(
        ModuleDocsInterface $module,
        SectionInterface $section,
        PageInterface $page
    ): array {
        $content = $this->pageRepository->getContentForSection(
            $module->getModuleName(),
            $section,
            $page->getRelativePath()
        ) ?? '';

        return [
            'module' => $module->getModuleName(),
            'moduleTitle' => $module->getTitle(),
            'section' => $section->getName(),
            'path' => $page->getRelativePath(),
            'title' => $page->getTitle(),
            'headings' => $this->headings($content),
            'body' => $this->body($content),
        ];
    }

    /**
     * The text of every markdown heading, one per line.
     *
     * @param string $content
     * @return string
     */
    private function headings(string $content): string
    {
        preg_match_all('/^#{1,6}\s+(.+)$/m', $content, $matches);

        return implode("\n", $matches[1]);
    }

    /**
     * The page text without code samples and without link addresses.
     *
     * @param string $content
     * @return string
     */
    private function body(string $content): string
    {
        foreach (self::NOISE_PATTERNS as $pattern => $replacement) {
            $stripped = preg_replace($pattern, $replacement, $content);
            $content = is_string($stripped) ? $stripped : $content;
        }

        return trim($content);
    }
}
