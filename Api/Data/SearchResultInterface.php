<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api\Data;

/**
 * Search result item interface
 */
interface SearchResultInterface
{
    /**
     * Get module name
     *
     * @return string
     */
    public function getModule(): string;

    /**
     * Get module display title
     *
     * @return string
     */
    public function getModuleTitle(): string;

    /**
     * Get feature name
     *
     * @return string
     */
    public function getFeature(): string;

    /**
     * Get file path
     *
     * @return string
     */
    public function getFile(): string;

    /**
     * Get display name
     *
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * Get URL to the documentation
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * Get breadcrumb path
     *
     * @return string
     */
    public function getBreadcrumb(): string;

    /**
     * Convert to array
     *
     * @return array<string, string>
     */
    public function toArray(): array;
}
