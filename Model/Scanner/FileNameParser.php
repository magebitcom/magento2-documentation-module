<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Scanner;

/**
 * Reads the sort order and label out of a "10-getting-started.md" style name.
 */
class FileNameParser
{
    private const DEFAULT_SORT_ORDER = 1000;

    private const INDEX_NAMES = ['index', 'readme'];

    private const MARKDOWN_SUFFIX = '.md';

    /**
     * Split a file or directory name into its sort order and display label.
     *
     * @param string $fileName
     * @return array{sortOrder: int, label: string, isIndex: bool}
     */
    public function parse(string $fileName): array
    {
        $base = $this->stripMarkdownSuffix($fileName);
        $sortOrder = self::DEFAULT_SORT_ORDER;

        if (preg_match('/^(\d+)[-_](.+)$/', $base, $matches) === 1) {
            $sortOrder = (int)$matches[1];
            $base = $matches[2];
        }

        $isIndex = in_array(strtolower($base), self::INDEX_NAMES, true);

        return [
            'sortOrder' => $sortOrder,
            'label' => $isIndex ? 'Overview' : $this->toLabel($base),
            'isIndex' => $isIndex,
        ];
    }

    /**
     * Drop a trailing ".md", in any letter case. Every other name, dots and all, is left alone.
     *
     * @param string $fileName
     * @return string
     */
    private function stripMarkdownSuffix(string $fileName): string
    {
        if (!str_ends_with(strtolower($fileName), self::MARKDOWN_SUFFIX)) {
            return $fileName;
        }

        return substr($fileName, 0, -strlen(self::MARKDOWN_SUFFIX));
    }

    /**
     * Turn a de-prefixed name into a human readable label.
     *
     * @param string $base
     * @return string
     */
    private function toLabel(string $base): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $base));
    }
}
