<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api\Data;

/**
 * A single search result matching a documentation page.
 */
interface SearchHitInterface
{
    /**
     * The module the matched page belongs to.
     *
     * @return string
     */
    public function getModuleName(): string;

    /**
     * The display title of the module the matched page belongs to.
     *
     * @return string
     */
    public function getModuleTitle(): string;

    /**
     * The section the matched page belongs to.
     *
     * @return string
     */
    public function getSectionName(): string;

    /**
     * Path relative to the section root.
     *
     * @return string
     */
    public function getRelativePath(): string;

    /**
     * The title of the matched page.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Plain text, already truncated, not html.
     *
     * @return string
     */
    public function getSnippet(): string;

    /**
     * Human-readable path to the matched page, e.g. "Module > Section > Page".
     *
     * @return string
     */
    public function getBreadcrumb(): string;

    /**
     * The relevance score of this hit, higher is more relevant.
     *
     * @return int
     */
    public function getScore(): int;
}
