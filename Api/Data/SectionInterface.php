<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api\Data;

/**
 * One documentation.xml "section" entry for a module.
 */
interface SectionInterface
{
    /**
     * The documentation.xml "name" attribute.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * The raw configured path, e.g. "Vendor_B::Docs".
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * The ACL resource required to view this section, if any.
     *
     * @return string|null
     */
    public function getAcl(): ?string;

    /**
     * The position of this section among its siblings.
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * The root category holding this section's page tree.
     *
     * @return CategoryInterface
     */
    public function getRoot(): CategoryInterface;

    /**
     * Whether this section renders as a changelog instead of a page tree.
     *
     * @return bool
     */
    public function isChangelog(): bool;
}
