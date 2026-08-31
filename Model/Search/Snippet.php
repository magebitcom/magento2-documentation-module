<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Search;

/**
 * Cuts a short plain-text window out of a page body, centred on the first match.
 */
class Snippet
{
    private const ELLIPSIS = '...';

    /**
     * Extract a plain-text window around the first match.
     *
     * @param string $body
     * @param string $query
     * @param int $length
     * @return string
     */
    public function extract(string $body, string $query, int $length = 160): string
    {
        $body = $this->oneLine($body);
        $total = mb_strlen($body);

        if ($length < 1) {
            return '';
        }

        if ($total <= $length) {
            return $body;
        }

        $start = $this->windowStart($body, trim($query), $length, $total);
        $window = mb_substr($body, $start, $length);

        $prefix = $start > 0 ? self::ELLIPSIS : '';
        $suffix = $start + $length < $total ? self::ELLIPSIS : '';

        return $prefix . $window . $suffix;
    }

    /**
     * Where the window starts, so the first match sits in the middle of it.
     *
     * @param string $body
     * @param string $query
     * @param int $length
     * @param int $total
     * @return int
     */
    private function windowStart(string $body, string $query, int $length, int $total): int
    {
        $position = $query === '' ? false : mb_stripos($body, $query);

        if ($position === false) {
            return 0;
        }

        $start = (int)($position - ($length - mb_strlen($query)) / 2);

        return max(0, min($start, $total - $length));
    }

    /**
     * Squash every run of whitespace into a single space.
     *
     * @param string $body
     * @return string
     */
    private function oneLine(string $body): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $body);

        return trim(is_string($collapsed) ? $collapsed : $body);
    }
}
