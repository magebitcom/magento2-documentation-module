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
}
