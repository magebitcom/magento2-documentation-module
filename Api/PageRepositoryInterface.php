<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

use Magebit\Documentation\Api\Data\SectionInterface;

/**
 * Reads documentation pages off disk.
 */
interface PageRepositoryInterface
{
    /**
     * Raw markdown for a page, or null when it does not exist or is not permitted.
     *
     * @param string $moduleName
     * @param string $sectionName
     * @param string $relativePath
     * @return string|null
     */
    public function getContent(string $moduleName, string $sectionName, string $relativePath): ?string;

    /**
     * Raw markdown for a page of an already-resolved section.
     *
     * The caller is responsible for authorization — this bypasses the ACL-filtered tree.
     *
     * @param string $moduleName Module whose documentation.xml declared the section
     * @param SectionInterface $section
     * @param string $relativePath
     * @return string|null
     */
    public function getContentForSection(
        string $moduleName,
        SectionInterface $section,
        string $relativePath
    ): ?string;

    /**
     * Front matter for a page, empty when the page has none.
     *
     * @param string $moduleName
     * @param string $sectionName
     * @param string $relativePath
     * @return array<string, mixed>
     */
    public function getFrontMatter(string $moduleName, string $sectionName, string $relativePath): array;
}
