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
        '/\A\x{FEFF}?---\r?\n.*?\r?\n---[ \t]*(?:\r?\n|\z)/su' => ' ',
        '/```.*?```/s' => ' ',
        '/~~~.*?~~~/s' => ' ',
        '/`[^`]*`/' => ' ',
        '/!\[[^\]]*\]\([^)]*\)/' => ' ',
        '/\[([^\]]*)\]\([^)]*\)/' => '$1',
        '~<?\bhttps?://[^\s>]*>?~i' => ' ',
    ];

    /**
     * Emphasis markers, taken off the body so a snippet reads as prose.
     * Underscores stay, because module and method names are full of them.
     */
    private const EMPHASIS_PATTERNS = [
        '/\*+/' => '',
        '/~~/' => '',
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

        $cleaned = $this->replace($content, self::NOISE_PATTERNS);

        return [
            'module' => $module->getModuleName(),
            'moduleTitle' => $module->getTitle(),
            'section' => $section->getName(),
            'path' => $page->getRelativePath(),
            'title' => $page->getTitle(),
            'headings' => $this->headings($cleaned),
            'body' => $this->body($cleaned),
        ];
    }

    /**
     * The text of every markdown heading, one per line.
     *
     * @param string $cleaned Page text the noise patterns already ran over
     * @return string
     */
    private function headings(string $cleaned): string
    {
        preg_match_all('/^#{1,6}\s+(.+)$/m', $cleaned, $matches);

        return implode("\n", $matches[1]);
    }

    /**
     * The page text without its emphasis markers.
     *
     * @param string $cleaned Page text the noise patterns already ran over
     * @return string
     */
    private function body(string $cleaned): string
    {
        return trim($this->replace($cleaned, self::EMPHASIS_PATTERNS));
    }

    /**
     * Run a set of patterns over the text, keeping the text as it was when one of them fails.
     *
     * @param string $content
     * @param array<string,string> $patterns
     * @return string
     */
    private function replace(string $content, array $patterns): string
    {
        foreach ($patterns as $pattern => $replacement) {
            $stripped = preg_replace($pattern, $replacement, $content);
            $content = is_string($stripped) ? $stripped : $content;
        }

        return $content;
    }
}
