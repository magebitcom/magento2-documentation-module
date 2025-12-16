<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model;

use Magebit\Documentation\Api\DocumentationProviderInterface;
use Magebit\Documentation\Api\FileSystemScannerInterface;
use Magebit\Documentation\Model\Config\Data as DocumentationConfig;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;

class DocumentationProvider implements DocumentationProviderInterface
{
    /**
     * @var array<string, array<string, array{path: string, acl: string|null, structure: array}>>|null
     */
    private ?array $documentationTree = null;

    /**
     * @param DocumentationConfig $documentationConfig
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param FileSystemScannerInterface $fileSystemScanner
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly DocumentationConfig $documentationConfig,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly FileSystemScannerInterface $fileSystemScanner,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDocumentationTree(): array
    {
        if ($this->documentationTree !== null) {
            return $this->documentationTree;
        }

        $this->documentationTree = [];
        $allConfigs = $this->documentationConfig->get();

        if (!is_array($allConfigs)) {
            return $this->documentationTree;
        }

        foreach ($allConfigs as $moduleName => $moduleData) {
            if (!is_array($moduleData)) {
                continue;
            }

            $moduleMeta = [
                'title' => $moduleData['_title'] ?? null,
                'sortOrder' => $moduleData['_sortOrder'] ?? 100,
                'icon' => $moduleData['_icon'] ?? null,
            ];

            foreach ($moduleData as $featureName => $feature) {
                if (str_starts_with($featureName, '_')) {
                    continue;
                }
                $this->processFeature($moduleName, $featureName, $feature, $moduleMeta);
            }

            // Process changelog if present
            if (isset($moduleData['_changelog'])) {
                $this->processChangelog($moduleName, $moduleData['_changelog'], $moduleMeta);
            }
        }

        $this->sortDocumentationTree();

        return $this->documentationTree;
    }

    /**
     * Process a single documentation feature
     *
     * @param string $moduleName
     * @param string $featureName
     * @param array<string, mixed> $feature
     * @param array<string, mixed> $moduleMeta
     * @return void
     */
    private function processFeature(
        string $moduleName,
        string $featureName,
        array $feature,
        array $moduleMeta
    ): void {
        $path = $feature['path'] ?? null;
        if (!$path) {
            return;
        }

        // Check ACL permission
        $acl = $feature['acl'] ?? null;
        if ($acl && !$this->authorization->isAllowed($acl)) {
            return;
        }

        $docsPath = $this->resolveDocumentationPath($moduleName, $path);
        if (!$docsPath) {
            return;
        }

        $structure = $this->fileSystemScanner->scanDirectory($docsPath);

        if (!empty($structure['files']) || !empty($structure['categories'])) {
            if (!isset($this->documentationTree[$moduleName])) {
                $this->documentationTree[$moduleName] = [
                    '_title' => $moduleMeta['title'],
                    '_sortOrder' => $moduleMeta['sortOrder'],
                    '_icon' => $moduleMeta['icon'],
                ];
            }
            $this->documentationTree[$moduleName][$featureName] = [
                'path' => $path,
                'acl' => $acl,
                'sortOrder' => $feature['sortOrder'] ?? 100,
                'structure' => $structure,
                'type' => 'documentation',
            ];
        }
    }

    /**
     * Process changelog
     *
     * @param string $moduleName
     * @param array<string, mixed> $changelog
     * @param array<string, mixed> $moduleMeta
     * @return void
     */
    private function processChangelog(
        string $moduleName,
        array $changelog,
        array $moduleMeta
    ): void {
        $path = $changelog['path'] ?? null;
        if (!$path) {
            return;
        }

        // Check ACL permission
        $acl = $changelog['acl'] ?? null;
        if ($acl && !$this->authorization->isAllowed($acl)) {
            return;
        }

        $changelogPath = $this->resolveDocumentationPath($moduleName, $path);
        if (!$changelogPath || !file_exists($changelogPath)) {
            return;
        }

        if (!isset($this->documentationTree[$moduleName])) {
            $this->documentationTree[$moduleName] = [
                '_title' => $moduleMeta['title'],
                '_sortOrder' => $moduleMeta['sortOrder'],
                '_icon' => $moduleMeta['icon'],
            ];
        }

        $this->documentationTree[$moduleName]['_changelog'] = [
            'path' => $path,
            'acl' => $acl,
            'type' => 'changelog',
            'file' => basename($path),
        ];
    }

    /**
     * Resolve documentation path from various formats
     *
     * Supports:
     * - Module path: "Magebit_Module::Docs"
     * - Relative path: "Docs"
     * - Relative sibling: "../ModuleName/Docs"
     *
     * @param string $contextModuleName Module that defines the documentation
     * @param string $path Path specification
     * @return string|null Absolute filesystem path or null if invalid
     */
    private function resolveDocumentationPath(string $contextModuleName, string $path): ?string
    {
        // Module path format: "Magebit_Module::path"
        if (str_contains($path, '::')) {
            [$targetModule, $relativePath] = explode('::', $path, 2);
            $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $targetModule);
            if (!$modulePath) {
                return null;
            }
            return $modulePath . '/' . $relativePath;
        }

        // Relative or absolute path
        $contextModulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $contextModuleName);
        if (!$contextModulePath) {
            return null;
        }

        return $contextModulePath . '/' . $path;
    }

    /**
     * Sort documentation tree by sort order
     *
     * @return void
     */
    private function sortDocumentationTree(): void
    {
        // Sort modules by sort order
        uasort($this->documentationTree, function ($a, $b) {
            $sortA = $a['_sortOrder'] ?? 100;
            $sortB = $b['_sortOrder'] ?? 100;
            return $sortA <=> $sortB;
        });

        // Sort features within each module
        foreach ($this->documentationTree as $moduleName => &$moduleData) {
            $meta = [
                '_title' => $moduleData['_title'] ?? null,
                '_sortOrder' => $moduleData['_sortOrder'] ?? 100,
                '_icon' => $moduleData['_icon'] ?? null,
            ];

            // Preserve changelog if present
            $changelog = $moduleData['_changelog'] ?? null;

            $features = array_filter($moduleData, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);

            uasort($features, function ($a, $b) {
                $sortA = $a['sortOrder'] ?? 100;
                $sortB = $b['sortOrder'] ?? 100;
                return $sortA <=> $sortB;
            });

            $this->documentationTree[$moduleName] = array_merge($meta, $features);

            // Add changelog back if it exists
            if ($changelog) {
                $this->documentationTree[$moduleName]['_changelog'] = $changelog;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getFileContent(string $moduleName, string $featurePath, string $relativePath): ?string
    {
        // Verify ACL access before returning content
        $tree = $this->getDocumentationTree();
        $hasAccess = false;

        if (isset($tree[$moduleName])) {
            foreach ($tree[$moduleName] as $featureName => $feature) {
                if (str_starts_with($featureName, '_') && $featureName !== '_changelog') {
                    continue;
                }
                if ($feature['path'] === $featurePath) {
                    $hasAccess = true;
                    break;
                }
            }
        }

        if (!$hasAccess) {
            return null;
        }

        // Resolve the documentation path (handles Module::path format)
        $docsBasePath = $this->resolveDocumentationPath($moduleName, $featurePath);
        if (!$docsBasePath) {
            return null;
        }

        // For changelog, the path is directly to the file
        $isChangelog = isset($tree[$moduleName]['_changelog']) &&
                      $tree[$moduleName]['_changelog']['path'] === $featurePath;

        if ($isChangelog) {
            $fullPath = $docsBasePath;
        } else {
            // Build full file path
            $fullPath = $docsBasePath . '/' . $relativePath;
        }

        $realPath = realpath($fullPath);

        if ($realPath === false) {
            return null;
        }

        // Security: ensure path is within the module
        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
        if (!$modulePath || !str_starts_with($realPath, $modulePath)) {
            return null;
        }

        // Check if it's a markdown file
        if (!str_ends_with(strtolower($realPath), '.md')) {
            return null;
        }

        try {
            return file_get_contents($realPath);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getFirstFile(): ?array
    {
        $tree = $this->getDocumentationTree();

        foreach ($tree as $moduleName => $moduleData) {
            foreach ($moduleData as $featureName => $feature) {
                if (str_starts_with($featureName, '_')) {
                    continue;
                }
                $firstFile = $this->findFirstFileInStructure($feature['structure'], '');
                if ($firstFile !== null) {
                    return [
                        'module' => $moduleName,
                        'feature' => $featureName,
                        'featurePath' => $feature['path'],
                        'file' => $firstFile,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Find first file in structure recursively
     *
     * @param array<string, mixed> $structure
     * @param string $prefix
     * @return string|null
     */
    private function findFirstFileInStructure(array $structure, string $prefix): ?string
    {
        if (!empty($structure['files'])) {
            return $prefix . $structure['files'][0];
        }

        foreach ($structure['categories'] as $categoryName => $categoryStructure) {
            $file = $this->findFirstFileInStructure($categoryStructure, $prefix . $categoryName . '/');
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }
}
