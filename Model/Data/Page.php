<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Data;

use Magebit\Documentation\Api\Data\PageInterface;

class Page implements PageInterface
{
    /**
     * @param string $relativePath
     * @param string $fileName
     * @param string $title
     * @param int $sortOrder
     * @param bool $isIndex
     */
    public function __construct(
        private readonly string $relativePath,
        private readonly string $fileName,
        private readonly string $title,
        private readonly int $sortOrder,
        private readonly bool $isIndex
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    /**
     * @inheritDoc
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title;
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
    public function isIndex(): bool
    {
        return $this->isIndex;
    }
}
