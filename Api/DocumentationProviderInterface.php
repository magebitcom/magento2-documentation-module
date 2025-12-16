<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

interface DocumentationProviderInterface
{
    /**
     * Get the full documentation tree structure
     *
     * @return array<string, array<string, array{path: string, acl: string|null, structure: array}>>
     */
    public function getDocumentationTree(): array;

    /**
     * Get documentation file content
     *
     * @param string $moduleName
     * @param string $featurePath
     * @param string $relativePath
     * @return string|null
     */
    public function getFileContent(string $moduleName, string $featurePath, string $relativePath): ?string;

    /**
     * Get first available documentation file
     *
     * @return array{module: string, feature: string, featurePath: string, file: string}|null
     */
    public function getFirstFile(): ?array;
}
