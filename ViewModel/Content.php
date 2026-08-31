<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\ViewModel;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\PageInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Api\MarkdownRendererInterface;
use Magebit\Documentation\Api\PageRepositoryInterface;
use Magebit\Documentation\Api\SyntaxHighlighterInterface;
use Magebit\Documentation\Model\CurrentPage;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Feeds the page body, its breadcrumb and the links to the pages around it.
 */
class Content implements ArgumentInterface
{
    /**
     * @param CurrentPage $currentPage
     * @param DocumentationTreeInterface $tree
     * @param PageRepositoryInterface $pageRepository
     * @param MarkdownRendererInterface $renderer
     * @param SyntaxHighlighterInterface $highlighter
     * @param UrlBuilder $urlBuilder
     */
    public function __construct(
        private readonly CurrentPage $currentPage,
        private readonly DocumentationTreeInterface $tree,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly MarkdownRendererInterface $renderer,
        private readonly SyntaxHighlighterInterface $highlighter,
        private readonly UrlBuilder $urlBuilder
    ) {
    }

    /**
     * The page body as HTML, empty when the page cannot be read.
     *
     * @return string
     */
    public function getRenderedContent(): string
    {
        $current = $this->currentPage->get();

        if ($current === null) {
            return '';
        }

        $markdown = $this->pageRepository->getContent($current['module'], $current['section'], $current['path']);

        if ($markdown === null) {
            return '';
        }

        return $this->highlighter->decorate($this->renderer->render($markdown, $current));
    }

    /**
     * The module, the section and the page, as far as each of them is known.
     *
     * @return list<string>
     */
    public function getBreadcrumb(): array
    {
        $current = $this->currentPage->get();

        if ($current === null) {
            return [];
        }

        $module = $this->tree->get()[$current['module']] ?? null;
        $section = $this->tree->getSection($current['module'], $current['section']);

        if ($module === null || $section === null) {
            return [];
        }

        $crumbs = [$module->getTitle(), $section->getName()];
        $page = $this->pageAt($section, $current['path']);

        if ($page !== null) {
            $crumbs[] = $page->getTitle();
        }

        return $crumbs;
    }

    /**
     * The page before the current one in its section.
     *
     * @return array{title: string, url: string}|null
     */
    public function getPrevious(): ?array
    {
        return $this->neighbour(-1);
    }

    /**
     * The page after the current one in its section.
     *
     * @return array{title: string, url: string}|null
     */
    public function getNext(): ?array
    {
        return $this->neighbour(1);
    }

    /**
     * The page a given number of steps away from the current one, in section order.
     *
     * @param int $step
     * @return array{title: string, url: string}|null
     */
    private function neighbour(int $step): ?array
    {
        $current = $this->currentPage->get();

        if ($current === null) {
            return null;
        }

        $section = $this->tree->getSection($current['module'], $current['section']);

        if ($section === null) {
            return null;
        }

        $pages = $this->flatten($section->getRoot());
        $position = null;

        foreach ($pages as $index => $page) {
            if ($page->getRelativePath() === $current['path']) {
                $position = $index;
                break;
            }
        }

        $neighbour = $position === null ? null : ($pages[$position + $step] ?? null);

        if ($neighbour === null) {
            return null;
        }

        return [
            'title' => $neighbour->getTitle(),
            'url' => $this->urlBuilder->page(
                $current['module'],
                $current['section'],
                $neighbour->getRelativePath()
            ),
        ];
    }

    /**
     * The page with a given path, or nothing when the section does not hold it.
     *
     * @param SectionInterface $section
     * @param string $relativePath
     * @return PageInterface|null
     */
    private function pageAt(SectionInterface $section, string $relativePath): ?PageInterface
    {
        foreach ($this->flatten($section->getRoot()) as $page) {
            if ($page->getRelativePath() === $relativePath) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Every page of a category in the order the sidebar shows them.
     *
     * @param CategoryInterface $category
     * @return list<PageInterface>
     */
    private function flatten(CategoryInterface $category): array
    {
        $pages = $category->getPages();

        foreach ($category->getCategories() as $child) {
            foreach ($this->flatten($child) as $page) {
                $pages[] = $page;
            }
        }

        return $pages;
    }
}
