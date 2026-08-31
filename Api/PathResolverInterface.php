<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

/**
 * Turns configured documentation paths into absolute disk paths.
 */
interface PathResolverInterface
{
    /**
     * Resolve a configured section path to an absolute directory.
     *
     * @param string $contextModule Module whose documentation.xml declared the path
     * @param string $configuredPath "Docs", "Vendor_B::Docs" or "Docs/Guide"
     * @return string|null Absolute directory, or null when it does not resolve
     */
    public function resolveSectionRoot(string $contextModule, string $configuredPath): ?string;

    /**
     * Resolve a file inside a section root, refusing anything that escapes it.
     *
     * @param string $sectionRoot Absolute directory from resolveSectionRoot()
     * @param string $relativePath Untrusted path from the request
     * @param list<string> $allowedExtensions Lowercase, without the dot
     * @return string|null Absolute file path, or null when refused
     */
    public function resolveFile(string $sectionRoot, string $relativePath, array $allowedExtensions): ?string;

    /**
     * Resolve a changelog entry, whose configured path points at a file rather than a directory.
     *
     * @param string $contextModule Module whose documentation.xml declared the path
     * @param string $configuredPath "CHANGELOG.md" or "Vendor_B::docs/CHANGELOG.md"
     * @return array{path: string, fileName: string}|null Absolute path and bare file name, or null when refused
     */
    public function resolveChangelogFile(string $contextModule, string $configuredPath): ?array;
}
