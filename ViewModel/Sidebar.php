<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\ViewModel;

use Magebit\Documentation\Api\DocumentationProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for documentation sidebar
 */
class Sidebar implements ArgumentInterface
{
    /**
     * @param DocumentationProviderInterface $documentationProvider
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly DocumentationProviderInterface $documentationProvider,
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Get documentation tree structure
     *
     * @return array<string, array<string, array>>
     */
    public function getDocumentationTree(): array
    {
        return $this->documentationProvider->getDocumentationTree();
    }

    /**
     * Get current module from request
     *
     * @return string|null
     */
    public function getCurrentModule(): ?string
    {
        return $this->request->getParam('module');
    }

    /**
     * Get current feature from request
     *
     * @return string|null
     */
    public function getCurrentFeature(): ?string
    {
        return $this->request->getParam('feature');
    }

    /**
     * Get current file from request
     *
     * @return string|null
     */
    public function getCurrentFile(): ?string
    {
        return $this->request->getParam('file');
    }

    /**
     * Get URL for documentation file
     *
     * @param string $module
     * @param string $feature
     * @param string $file
     * @return string
     */
    public function getDocUrl(string $module, string $feature, string $file): string
    {
        $params = [
            'module' => $module,
            'feature' => $feature,
            'file' => $file,
        ];

        // Preserve expand state if set
        $expand = $this->request->getParam('expand');
        if ($expand) {
            $params['expand'] = $expand;
        }

        // Preserve expanded modules if set
        $expandedModules = $this->request->getParam('expanded_modules');
        if ($expandedModules) {
            $params['expanded_modules'] = $expandedModules;
        }

        return $this->urlBuilder->getUrl('magebit_documentation/index/index', $params);
    }

    /**
     * Check if current selection matches given parameters
     *
     * @param string $module
     * @param string $feature
     * @param string $file
     * @return bool
     */
    public function isActive(string $module, string $feature, string $file): bool
    {
        $currentModule = $this->getCurrentModule();
        $currentFeature = $this->getCurrentFeature();
        $currentFile = $this->getCurrentFile();

        if (!$currentModule || !$currentFeature || !$currentFile) {
            $first = $this->documentationProvider->getFirstFile();
            if ($first) {
                $currentModule = $first['module'];
                $currentFeature = $first['feature'];
                $currentFile = $first['file'];
            }
        }

        return $currentModule === $module && $currentFeature === $feature && $currentFile === $file;
    }

    /**
     * Format file name for display
     *
     * Strips numeric prefixes (e.g., "1-", "02_") and converts to title case
     *
     * @param string $fileName
     * @return string
     */
    public function formatFileName(string $fileName): string
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);

        // Strip numeric prefix pattern: "123-" or "123_"
        if (preg_match('/^\d+[-_](.+)$/', $name, $matches)) {
            $name = $matches[1];
        }

        // Index files show as "Overview"
        if (strtolower($name) === 'index') {
            return 'Overview';
        }

        return ucwords(str_replace(['-', '_'], ' ', $name));
    }
}
