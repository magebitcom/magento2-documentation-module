<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Data;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\SectionInterface;

class Section implements SectionInterface
{
    /**
     * @param string $name
     * @param string $path
     * @param string|null $acl
     * @param int $sortOrder
     * @param CategoryInterface $root
     * @param bool $isChangelog
     */
    public function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly ?string $acl,
        private readonly int $sortOrder,
        private readonly CategoryInterface $root,
        private readonly bool $isChangelog
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function getAcl(): ?string
    {
        return $this->acl;
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
    public function getRoot(): CategoryInterface
    {
        return $this->root;
    }

    /**
     * @inheritDoc
     */
    public function isChangelog(): bool
    {
        return $this->isChangelog;
    }
}
