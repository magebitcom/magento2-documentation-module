<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

interface MarkdownRendererInterface
{
    /**
     * Render markdown content to HTML
     *
     * @param string $markdown
     * @param array<string, string> $context Optional context for link processing
     * @return string
     */
    public function render(string $markdown, array $context = []): string;
}
