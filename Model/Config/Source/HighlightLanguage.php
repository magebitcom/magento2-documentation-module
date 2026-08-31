<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Config\Source;

/**
 * The extra highlight.js language bundles shipped with the module.
 */
class HighlightLanguage extends ShippedFiles
{
    /**
     * @inheritDoc
     */
    protected function getDirectory(): string
    {
        return 'view/adminhtml/web/js/vendor/highlight/languages';
    }

    /**
     * @inheritDoc
     */
    protected function getSuffix(): string
    {
        return '.min.js';
    }
}
