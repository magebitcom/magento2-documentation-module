<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api\Data;

/**
 * All documentation sections registered by a single module.
 */
interface ModuleDocsInterface
{
    /**
     * The module name, e.g. "Vendor_Module".
     *
     * @return string
     */
    public function getModuleName(): string;

    /**
     * Falls back to the module name when no title is configured.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * View-asset path for this module's menu icon, e.g. "Magebit_Documentation::images/icon.svg".
     *
     * @return string|null
     */
    public function getIcon(): ?string;

    /**
     * The position of this module among its siblings.
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * The documentation sections registered by this module.
     *
     * @return SectionInterface[]
     */
    public function getSections(): array;
}
