<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\PageInterface;
use Magebit\Documentation\Api\Data\SectionInterface;

/**
 * The documentation tree as the current admin is allowed to see it.
 */
interface DocumentationTreeInterface
{
    /**
     * Full tree, filtered to what the current admin may see.
     *
     * @return array<string, ModuleDocsInterface> Keyed by module name, ordered by sort order
     */
    public function get(): array;

    /**
     * One section of one module, or nothing when it does not exist or is not permitted.
     *
     * @param string $moduleName
     * @param string $sectionName
     * @return SectionInterface|null
     */
    public function getSection(string $moduleName, string $sectionName): ?SectionInterface;

    /**
     * First page of the first section of the first module the admin may see.
     *
     * @return array{module: ModuleDocsInterface, section: SectionInterface, page: PageInterface}|null
     */
    public function getFirst(): ?array;
}
