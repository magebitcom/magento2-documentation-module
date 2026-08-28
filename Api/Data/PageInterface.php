<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api\Data;

/**
 * A single documentation markdown file.
 */
interface PageInterface
{
    /**
     * Path relative to the section root, e.g. "advanced/1-api.md".
     *
     * @return string
     */
    public function getRelativePath(): string;

    /**
     * The file name only, e.g. "1-api.md".
     *
     * @return string
     */
    public function getFileName(): string;

    /**
     * Front matter title, or the de-prefixed file name when there is none.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * The position of this page among its siblings.
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * Whether this page is the index page of its category.
     *
     * @return bool
     */
    public function isIndex(): bool;
}
