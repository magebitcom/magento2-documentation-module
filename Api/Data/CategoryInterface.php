<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api\Data;

/**
 * A folder of pages and sub-categories inside a documentation section.
 */
interface CategoryInterface
{
    /**
     * The category label, empty string for a section root.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * The position of this category among its siblings.
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * The pages directly inside this category.
     *
     * @return PageInterface[]
     */
    public function getPages(): array;

    /**
     * The sub-categories directly inside this category, as an ordered list (not keyed by name).
     *
     * @return CategoryInterface[]
     */
    public function getCategories(): array;

    /**
     * Whether this category has no pages and no sub-categories.
     *
     * @return bool
     */
    public function isEmpty(): bool;
}
