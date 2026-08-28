<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

use Magebit\Documentation\Api\Data\CategoryInterface;

/**
 * Turns a documentation directory into a category tree.
 */
interface DirectoryScannerInterface
{
    /**
     * Scan a directory into a category tree of markdown pages.
     *
     * @param string $absoluteRoot Absolute, already validated by PathResolverInterface
     * @return CategoryInterface Root category with an empty label
     */
    public function scan(string $absoluteRoot): CategoryInterface;
}
