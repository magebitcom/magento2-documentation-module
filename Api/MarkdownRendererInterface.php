<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

/**
 * Turns the markdown of one documentation page into HTML.
 */
interface MarkdownRendererInterface
{
    /**
     * Render markdown to HTML, with links and images pointing at the page they belong to.
     *
     * @param string $markdown
     * @param array{module:string,section:string,path:string} $context Page the markdown came from
     * @return string HTML, or an empty string when the markdown could not be parsed
     */
    public function render(string $markdown, array $context): string;
}
