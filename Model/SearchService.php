<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model;

use Magebit\Documentation\Api\Data\SearchResultInterface;
use Magebit\Documentation\Api\DocumentationProviderInterface;
use Magebit\Documentation\Api\SearchServiceInterface;
use Magebit\Documentation\Model\Data\SearchResult;
use Magento\Framework\UrlInterface;

/**
 * Documentation search service implementation
 */
class SearchService implements SearchServiceInterface
{
    /**
     * @var array<string, string>
     */
    private array $additionalParams = [];

    /**
     * @param DocumentationProviderInterface $documentationProvider
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly DocumentationProviderInterface $documentationProvider,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function search(string $query, array $additionalParams = []): array
    {
        $this->additionalParams = $additionalParams;

        $query = trim(strtolower($query));
        if ($query === '') {
            return [];
        }

        $results = [];
        $tree = $this->documentationProvider->getDocumentationTree();

        foreach ($tree as $moduleName => $moduleData) {
            $moduleTitle = $moduleData['_title'] ?? $moduleName;

            foreach ($moduleData as $featureName => $feature) {
                if (str_starts_with($featureName, '_') && $featureName !== '_changelog') {
                    continue;
                }

                // Handle changelog separately (it's a single file, not a structure)
                if ($featureName === '_changelog') {
                    $displayName = 'Changelog';
                    if ($this->matchesQuery($displayName, $query)) {
                        $results[] = new SearchResult(
                            $moduleName,
                            $moduleTitle,
                            $featureName,
                            $feature['file'],
                            $displayName,
                            $this->buildUrl($moduleName, $featureName, $feature['file']),
                            $moduleTitle . ' › ' . $displayName
                        );
                    }
                    continue;
                }

                $this->searchInStructure(
                    $results,
                    $query,
                    $moduleName,
                    $moduleTitle,
                    $featureName,
                    $feature['structure'],
                    ''
                );
            }
        }

        return $results;
    }

    /**
     * Search recursively in structure
     *
     * @param SearchResultInterface[] $results
     * @param string $query
     * @param string $moduleName
     * @param string $moduleTitle
     * @param string $featureName
     * @param array<string, mixed> $structure
     * @param string $prefix
     * @return void
     */
    private function searchInStructure(
        array &$results,
        string $query,
        string $moduleName,
        string $moduleTitle,
        string $featureName,
        array $structure,
        string $prefix
    ): void {
        // Search in files
        foreach ($structure['files'] ?? [] as $file) {
            $filePath = $prefix . $file;
            $displayName = $this->formatFileName($file);

            if ($this->matchesQuery($displayName, $query)) {
                $results[] = new SearchResult(
                    $moduleName,
                    $moduleTitle,
                    $featureName,
                    $filePath,
                    $displayName,
                    $this->buildUrl($moduleName, $featureName, $filePath),
                    $this->buildBreadcrumb($moduleTitle, $featureName, $prefix, $displayName)
                );
            }
        }

        // Search in categories recursively
        foreach ($structure['categories'] ?? [] as $categoryName => $categoryStructure) {
            $this->searchInStructure(
                $results,
                $query,
                $moduleName,
                $moduleTitle,
                $featureName,
                $categoryStructure,
                $prefix . $categoryName . '/'
            );
        }
    }

    /**
     * Check if display name matches query
     *
     * @param string $displayName
     * @param string $query
     * @return bool
     */
    private function matchesQuery(string $displayName, string $query): bool
    {
        return str_contains(strtolower($displayName), $query);
    }

    /**
     * Format file name for display
     *
     * Strips numeric prefixes (e.g., "1-", "02_") and converts to title case
     *
     * @param string $fileName
     * @return string
     */
    private function formatFileName(string $fileName): string
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);

        // Strip numeric prefix pattern: "123-" or "123_"
        if (preg_match('/^\d+[-_](.+)$/', $name, $matches)) {
            $name = $matches[1];
        }

        if (strtolower($name) === 'index') {
            return 'Overview';
        }

        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Build URL for documentation file
     *
     * @param string $module
     * @param string $feature
     * @param string $file
     * @return string
     */
    private function buildUrl(string $module, string $feature, string $file): string
    {
        $params = [
            'module' => $module,
            'feature' => $feature,
            'file' => $file,
        ];

        // Merge with additional params (e.g., expand state)
        $params = array_merge($params, $this->additionalParams);

        return $this->urlBuilder->getUrl('magebit_documentation/index/index', $params);
    }

    /**
     * Build breadcrumb path
     *
     * @param string $moduleTitle
     * @param string $featureName
     * @param string $prefix
     * @param string $displayName
     * @return string
     */
    private function buildBreadcrumb(
        string $moduleTitle,
        string $featureName,
        string $prefix,
        string $displayName
    ): string {
        $parts = [$moduleTitle, $featureName];

        if ($prefix !== '') {
            $categories = explode('/', trim($prefix, '/'));
            $parts = array_merge($parts, $categories);
        }

        $parts[] = $displayName;

        return implode(' › ', $parts);
    }
}
