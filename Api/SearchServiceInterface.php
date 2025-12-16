<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

use Magebit\Documentation\Api\Data\SearchResultInterface;

/**
 * Documentation search service interface
 */
interface SearchServiceInterface
{
    /**
     * Search documentation by query
     *
     * @param string $query
     * @param array<string, string> $additionalParams Additional URL parameters to include
     * @return SearchResultInterface[]
     */
    public function search(string $query, array $additionalParams = []): array;
}
