<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\ViewModel;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Model\CurrentPage;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Feeds the documentation tree in the sidebar.
 */
class Sidebar implements ArgumentInterface
{
    /**
     * @param DocumentationTreeInterface $tree
     * @param CurrentPage $currentPage
     * @param UrlBuilder $urlBuilder
     */
    public function __construct(
        private readonly DocumentationTreeInterface $tree,
        private readonly CurrentPage $currentPage,
        private readonly UrlBuilder $urlBuilder
    ) {
    }

    /**
     * The documentation tree, already filtered to what the current admin may see.
     *
     * @return array<string, ModuleDocsInterface> Keyed by module name, ordered by sort order
     */
    public function getTree(): array
    {
        return $this->tree->get();
    }

    /**
     * The URL of one page in the tree.
     *
     * @param string $moduleName
     * @param string $sectionName
     * @param string $relativePath
     * @return string
     */
    public function getPageUrl(string $moduleName, string $sectionName, string $relativePath): string
    {
        return $this->urlBuilder->page($moduleName, $sectionName, $relativePath);
    }

    /**
     * Whether a tree entry is the page being shown.
     *
     * @param string $moduleName
     * @param string $sectionName
     * @param string $relativePath
     * @return bool
     */
    public function isActive(string $moduleName, string $sectionName, string $relativePath): bool
    {
        $current = $this->currentPage->get();

        return $current !== null
            && $current['module'] === $moduleName
            && $current['section'] === $sectionName
            && $current['path'] === $relativePath;
    }

    /**
     * The page being shown, or nothing when there is no documentation at all.
     *
     * @return array{module: string, section: string, path: string}|null
     */
    public function getCurrent(): ?array
    {
        return $this->currentPage->get();
    }
}
