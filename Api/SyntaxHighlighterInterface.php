<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

/**
 * Prepares rendered documentation HTML for syntax highlighting.
 */
interface SyntaxHighlighterInterface
{
    /**
     * Decorate rendered documentation HTML with whatever the highlighter needs.
     *
     * @param string $html
     * @return string
     */
    public function decorate(string $html): string;
}
