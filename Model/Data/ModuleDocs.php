<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Data;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\SectionInterface;

class ModuleDocs implements ModuleDocsInterface
{
    /**
     * @param string $moduleName
     * @param string $title
     * @param string|null $icon
     * @param int $sortOrder
     * @param SectionInterface[] $sections
     */
    public function __construct(
        private readonly string $moduleName,
        private readonly string $title,
        private readonly ?string $icon,
        private readonly int $sortOrder,
        private readonly array $sections
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title !== '' ? $this->title : $this->moduleName;
    }

    /**
     * @inheritDoc
     */
    public function getIcon(): ?string
    {
        return $this->icon;
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
    public function getSections(): array
    {
        return $this->sections;
    }
}
