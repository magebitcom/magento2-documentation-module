<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Data;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\PageInterface;

class Category implements CategoryInterface
{
    /**
     * @param string $label
     * @param int $sortOrder
     * @param PageInterface[] $pages
     * @param CategoryInterface[] $categories
     */
    public function __construct(
        private readonly string $label,
        private readonly int $sortOrder,
        private readonly array $pages,
        private readonly array $categories
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @inheritDoc
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * @inheritDoc
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return $this->pages === [] && $this->categories === [];
    }
}
