<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

interface FileSystemScannerInterface
{
    /**
     * Scan a directory for markdown files and subdirectories
     *
     * @param string $basePath
     * @return array{files: string[], categories: array<string, array>}
     */
    public function scanDirectory(string $basePath): array;

    /**
     * Read file content safely
     *
     * @param string $modulePath
     * @param string $featurePath
     * @param string $relativePath
     * @return string|null
     */
    public function readFile(string $modulePath, string $featurePath, string $relativePath): ?string;
}
