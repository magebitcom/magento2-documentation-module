<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Api;

use Magebit\Documentation\Api\Data\SearchHitInterface;

/**
 * Searches the documentation the current admin is allowed to see.
 */
interface SearchIndexInterface
{
    /**
     * Pages matching a query, best match first, limited to what the current admin may see.
     *
     * @param string $query
     * @param int $limit
     * @return list<SearchHitInterface> Highest score first
     */
    public function search(string $query, int $limit = 20): array;
}
