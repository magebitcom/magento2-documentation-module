<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Search;

use InvalidArgumentException;
use Magebit\Documentation\Api\Data\SearchHitInterface;
use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Api\SearchIndexInterface;
use Magebit\Documentation\Model\Cache\Type as CacheType;
use Magebit\Documentation\Model\Data\SearchHit;
use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Scores the cached index on every query and hides sections the current admin may not see.
 *
 * @phpstan-import-type SearchRecord from Indexer
 */
class Index implements SearchIndexInterface
{
    private const CACHE_KEY = 'magebit_documentation_search_index';

    private const MINIMUM_QUERY_LENGTH = 2;

    private const SNIPPET_LENGTH = 160;

    private const SCORE_EXACT_TITLE = 100;

    private const SCORE_TITLE = 60;

    private const SCORE_HEADING = 30;

    private const SCORE_BODY = 10;

    /**
     * @var list<SearchRecord>|null
     */
    private ?array $records = null;

    /**
     * @param Indexer $indexer
     * @param CacheType $cache
     * @param SerializerInterface $serializer
     * @param DocumentationTreeInterface $tree
     * @param Snippet $snippet
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Indexer $indexer,
        private readonly CacheType $cache,
        private readonly SerializerInterface $serializer,
        private readonly DocumentationTreeInterface $tree,
        private readonly Snippet $snippet,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function search(string $query, int $limit = 20): array
    {
        $needle = mb_strtolower(trim($query));

        if (mb_strlen($needle) < self::MINIMUM_QUERY_LENGTH || $limit < 1) {
            return [];
        }

        $hits = [];

        foreach ($this->records() as $record) {
            $score = $this->score($record, $needle);

            if ($score === 0 || $this->tree->getSection($record['module'], $record['section']) === null) {
                continue;
            }

            $hits[] = $this->hit($record, $needle, $score);
        }

        // Sorting is stable, so equal scores keep the order the indexer walked the tree in.
        usort(
            $hits,
            static fn (SearchHitInterface $a, SearchHitInterface $b): int => $b->getScore() <=> $a->getScore()
        );

        return array_slice($hits, 0, $limit);
    }

    /**
     * How well one record matches, by where the query was found.
     *
     * @param SearchRecord $record
     * @param string $needle Already trimmed and lower-cased
     * @return int Zero when it does not match at all
     */
    private function score(array $record, string $needle): int
    {
        $title = mb_strtolower($record['title']);

        if ($title === $needle) {
            return self::SCORE_EXACT_TITLE;
        }

        if (str_contains($title, $needle)) {
            return self::SCORE_TITLE;
        }

        if (str_contains(mb_strtolower($record['headings']), $needle)) {
            return self::SCORE_HEADING;
        }

        return str_contains(mb_strtolower($record['body']), $needle) ? self::SCORE_BODY : 0;
    }

    /**
     * Turn one matching record into a result, with a snippet cut around the match.
     *
     * @param SearchRecord $record
     * @param string $needle
     * @param int $score
     * @return SearchHitInterface
     */
    private function hit(array $record, string $needle, int $score): SearchHitInterface
    {
        return new SearchHit(
            $record['module'],
            $record['moduleTitle'],
            $record['section'],
            $record['path'],
            $record['title'],
            $this->snippet->extract($record['body'], $needle, self::SNIPPET_LENGTH),
            implode(' > ', [$record['moduleTitle'], $record['section'], $record['title']]),
            $score
        );
    }

    /**
     * The cached index, building and caching it when it is missing or no longer readable.
     *
     * @return list<SearchRecord>
     */
    private function records(): array
    {
        if ($this->records !== null) {
            return $this->records;
        }

        $cached = $this->readCache();

        if ($cached !== null) {
            return $this->records = $cached;
        }

        $records = $this->indexer->build();
        $serialized = $this->serializer->serialize($records);

        // Nothing worth caching when the index cannot be serialized; the next request rebuilds it.
        if (is_string($serialized)) {
            $this->cache->save($serialized, self::CACHE_KEY, [ConfigCacheType::CACHE_TAG]);
        }

        return $this->records = $records;
    }

    /**
     * Read the cached index, or nothing when it is missing or no longer matches the current shape.
     *
     * @return list<SearchRecord>|null
     */
    private function readCache(): ?array
    {
        $cached = $this->cache->load(self::CACHE_KEY);

        if (!is_string($cached) || $cached === '') {
            return null;
        }

        try {
            $data = $this->serializer->unserialize($cached);
        } catch (InvalidArgumentException $e) {
            $this->logger->warning(
                'Magebit_Documentation could not read the cached documentation search index.',
                ['exception' => $e->getMessage()]
            );

            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $records = [];

        foreach ($data as $row) {
            $record = $this->readRecord($row);

            if ($record === null) {
                return null;
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * Rebuild one record, or nothing when a field is missing or is not a string.
     *
     * @param mixed $data
     * @return SearchRecord|null
     */
    private function readRecord(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $module = $data['module'] ?? null;
        $moduleTitle = $data['moduleTitle'] ?? null;
        $section = $data['section'] ?? null;
        $path = $data['path'] ?? null;
        $title = $data['title'] ?? null;
        $headings = $data['headings'] ?? null;
        $body = $data['body'] ?? null;

        if (!is_string($module) || !is_string($moduleTitle) || !is_string($section) || !is_string($path)) {
            return null;
        }

        if (!is_string($title) || !is_string($headings) || !is_string($body)) {
            return null;
        }

        return [
            'module' => $module,
            'moduleTitle' => $moduleTitle,
            'section' => $section,
            'path' => $path,
            'title' => $title,
            'headings' => $headings,
            'body' => $body,
        ];
    }
}
