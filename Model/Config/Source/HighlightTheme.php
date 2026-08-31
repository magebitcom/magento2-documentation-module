<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Config\Source;

/**
 * The highlight.js themes shipped with the module.
 */
class HighlightTheme extends ShippedFiles
{
    /**
     * Ending of a theme file that belongs to another theme rather than standing on its own.
     */
    private const DARK_SUFFIX = '-dark';

    /**
     * @inheritDoc
     */
    protected function getDirectory(): string
    {
        return 'view/adminhtml/web/css/highlight';
    }

    /**
     * @inheritDoc
     */
    protected function getSuffix(): string
    {
        return '.css';
    }

    /**
     * Dark files pair up with a light theme, so they are never a choice of their own.
     *
     * @param string $name
     * @return bool
     */
    protected function isSelectable(string $name): bool
    {
        return !str_ends_with($name, self::DARK_SUFFIX);
    }
}
