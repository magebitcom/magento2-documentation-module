<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model;

use Magebit\Documentation\Api\FileSystemScannerInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

class FileSystemScanner implements FileSystemScannerInterface
{
    /**
     * @param FileDriver $fileDriver
     */
    public function __construct(
        private readonly FileDriver $fileDriver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function scanDirectory(string $basePath): array
    {
        $structure = [
            'files' => [],
            'categories' => [],
        ];

        try {
            if (!$this->fileDriver->isDirectory($basePath)) {
                return $structure;
            }

            $items = $this->fileDriver->readDirectory($basePath);

            foreach ($items as $item) {
                $name = basename($item);

                if ($this->fileDriver->isDirectory($item)) {
                    $categoryStructure = $this->scanDirectory($item);
                    if (!empty($categoryStructure['files']) || !empty($categoryStructure['categories'])) {
                        $structure['categories'][$name] = $categoryStructure;
                    }
                } elseif ($this->isMarkdownFile($name)) {
                    $structure['files'][] = $name;
                }
            }

            $structure['files'] = $this->sortFiles($structure['files']);
            $structure['categories'] = $this->sortCategories($structure['categories']);
        } catch (FileSystemException $e) {
            // Directory not readable - return empty structure
        }

        return $structure;
    }

    /**
     * @inheritDoc
     */
    public function readFile(string $modulePath, string $featurePath, string $relativePath): ?string
    {
        $fullPath = $modulePath . '/' . $featurePath . '/' . $relativePath;

        $realPath = realpath($fullPath);
        $realModulePath = realpath($modulePath);

        if ($realPath === false || $realModulePath === false) {
            return null;
        }

        // Security: ensure path is within module
        if (!str_starts_with($realPath, $realModulePath)) {
            return null;
        }

        if (!$this->isMarkdownFile($realPath)) {
            return null;
        }

        try {
            return $this->fileDriver->fileGetContents($realPath);
        } catch (FileSystemException $e) {
            return null;
        }
    }

    /**
     * Check if file has markdown extension
     *
     * @param string $path
     * @return bool
     */
    private function isMarkdownFile(string $path): bool
    {
        return str_ends_with(strtolower($path), '.md');
    }

    /**
     * Sort files: index first, then by numeric prefix, then alphabetically
     *
     * @param array<string> $files
     * @return array<string>
     */
    private function sortFiles(array $files): array
    {
        usort($files, function (string $a, string $b): int {
            $aInfo = $this->parseFileName($a);
            $bInfo = $this->parseFileName($b);

            // Index files always come first
            if ($aInfo['isIndex'] !== $bInfo['isIndex']) {
                return $aInfo['isIndex'] ? -1 : 1;
            }

            // Sort by numeric prefix
            if ($aInfo['sortOrder'] !== $bInfo['sortOrder']) {
                return $aInfo['sortOrder'] <=> $bInfo['sortOrder'];
            }

            // Fallback to alphabetical
            return strcasecmp($aInfo['name'], $bInfo['name']);
        });

        return $files;
    }

    /**
     * Sort categories by numeric prefix, then alphabetically
     *
     * @param array<string, array<string, mixed>> $categories
     * @return array<string, array<string, mixed>>
     */
    private function sortCategories(array $categories): array
    {
        uksort($categories, function (string $a, string $b): int {
            $aInfo = $this->parseFileName($a);
            $bInfo = $this->parseFileName($b);

            if ($aInfo['sortOrder'] !== $bInfo['sortOrder']) {
                return $aInfo['sortOrder'] <=> $bInfo['sortOrder'];
            }

            return strcasecmp($aInfo['name'], $bInfo['name']);
        });

        return $categories;
    }

    /**
     * Parse filename to extract sort order and clean name
     *
     * Supports formats:
     * - "1-filename.md" or "1_filename.md"
     * - "01-filename.md"
     * - "filename.md" (no prefix, default sort order 1000)
     *
     * @param string $fileName
     * @return array{sortOrder: int, name: string, isIndex: bool}
     */
    private function parseFileName(string $fileName): array
    {
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);

        // Check for numeric prefix pattern: "123-" or "123_"
        if (preg_match('/^(\d+)[-_](.+)$/', $baseName, $matches)) {
            $sortOrder = (int) $matches[1];
            $cleanName = $matches[2];
        } else {
            $sortOrder = 1000; // Default high value for unprefixed files
            $cleanName = $baseName;
        }

        $isIndex = strtolower($cleanName) === 'index';

        return [
            'sortOrder' => $sortOrder,
            'name' => $cleanName,
            'isIndex' => $isIndex,
        ];
    }
}
