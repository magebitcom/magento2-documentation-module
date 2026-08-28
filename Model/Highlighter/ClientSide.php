<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Highlighter;

use Magebit\Documentation\Api\SyntaxHighlighterInterface;

/**
 * Marks fenced code blocks for the browser, which does the highlighting with the bundled highlight.js.
 */
class ClientSide implements SyntaxHighlighterInterface
{
    private const HOOK_ATTRIBUTE = 'data-doc-highlight';

    /**
     * Matches the opening code tag of a fenced block, capturing its language class.
     */
    private const CODE_BLOCK_PATTERN = '~(<pre\b[^>]*>\s*<code\b)'
        . '([^>]*\bclass="[^"]*\blanguage-([A-Za-z0-9_+#.-]+)[^"]*"[^>]*)>~i';

    /**
     * @inheritDoc
     */
    public function decorate(string $html): string
    {
        $decorated = preg_replace_callback(
            self::CODE_BLOCK_PATTERN,
            static function (array $match): string {
                // Already decorated, so leave the block exactly as it is.
                if (stripos($match[2], self::HOOK_ATTRIBUTE) !== false) {
                    return $match[0];
                }

                return $match[1] . $match[2] . ' ' . self::HOOK_ATTRIBUTE . '="' . $match[3] . '">';
            },
            $html
        );

        return $decorated ?? $html;
    }
}
