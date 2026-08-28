<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Search;

use Magebit\Documentation\Api\Data\SearchHitInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Model\Cache\Type as CacheType;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\Search\Index;
use Magebit\Documentation\Model\Search\Indexer;
use Magebit\Documentation\Model\Search\Snippet;
use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type SearchRecord from Indexer
 */
class IndexTest extends TestCase
{
    /**
     * @var Indexer&MockObject
     */
    private Indexer $indexer;

    /**
     * @var CacheType&MockObject
     */
    private CacheType $cache;

    /**
     * @var DocumentationTreeInterface&MockObject
     */
    private DocumentationTreeInterface $tree;

    /**
     * @var Index
     */
    private Index $index;

    /**
     * @var string|null
     */
    private ?string $cached = null;

    /**
     * @var list<string>
     */
    private array $hidden = [];

    protected function setUp(): void
    {
        $this->indexer = $this->createMock(Indexer::class);
        $this->cache = $this->createMock(CacheType::class);
        $this->tree = $this->createMock(DocumentationTreeInterface::class);

        $this->cache->method('load')->willReturnCallback(fn (): string|false => $this->cached ?? false);
        $this->tree->method('getSection')->willReturnCallback(
            fn (string $module, string $section): ?SectionInterface => in_array(
                $module . '/' . $section,
                $this->hidden,
                true
            ) ? null : $this->section($section)
        );

        $this->index = new Index($this->indexer, $this->cache, new Json(), $this->tree, new Snippet());
    }

    public function testRanksTitleMatchesAboveBodyMatches(): void
    {
        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Shipping', '', 'the invoice is created here'),
            $this->record('Vendor_A', 'Guide', 'b.md', 'Invoice Handling', '', 'nothing relevant'),
        ]);

        $this->assertSame(['Invoice Handling', 'Shipping'], array_map(
            static fn ($hit) => $hit->getTitle(),
            $hits
        ));
    }

    public function testMatchesDocumentBodyContentNotOnlyFileNames(): void
    {
        $hits = $this->search('reindex', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', 'run the reindex command'),
        ]);

        $this->assertCount(1, $hits);
        $this->assertSame('Setup', $hits[0]->getTitle());
    }

    public function testIsCaseInsensitiveAndTrimsTheQuery(): void
    {
        $hits = $this->search('  ReIndex  ', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', 'run the reindex command'),
        ]);

        $this->assertCount(1, $hits);
    }

    public function testReturnsNothingForAQueryBelowTheMinimumLength(): void
    {
        $this->assertSame([], $this->search('a', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', 'a a a'),
        ]));
    }

    public function testRespectsTheLimit(): void
    {
        $records = [];
        for ($i = 0; $i < 30; $i++) {
            $records[] = $this->record('Vendor_A', 'Guide', "p{$i}.md", "Page {$i}", '', 'invoice');
        }

        $this->assertCount(5, $this->search('invoice', $records, 5));
    }

    public function testReturnsNothingForALimitBelowOne(): void
    {
        $this->assertSame([], $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', 'invoice'),
        ], 0));
    }

    public function testScoresEveryMatchLocationSoTheWorstMatchSortsLast(): void
    {
        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'd.md', 'Shipping', 'Packing Slips', 'the invoice lives here'),
            $this->record('Vendor_A', 'Guide', 'c.md', 'Refunds', 'Invoice Totals', 'nothing relevant'),
            $this->record('Vendor_A', 'Guide', 'b.md', 'Invoice Handling', 'Intro', 'nothing relevant'),
            $this->record('Vendor_A', 'Guide', 'a.md', 'Invoice', 'Intro', 'nothing relevant'),
        ]);

        $this->assertSame(
            ['Invoice', 'Invoice Handling', 'Refunds', 'Shipping'],
            array_map(static fn (SearchHitInterface $hit): string => $hit->getTitle(), $hits)
        );
        $this->assertSame(
            [100, 60, 30, 10],
            array_map(static fn (SearchHitInterface $hit): int => $hit->getScore(), $hits)
        );
    }

    public function testIgnoresTheCaseOfAnExactTitleMatch(): void
    {
        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'INVOICE', '', 'nothing relevant'),
        ]);

        $this->assertSame(100, $hits[0]->getScore());
    }

    public function testBreaksTiesOnTheOrderTheIndexerWalkedTheTree(): void
    {
        $hits = $this->search('invoice', [
            $this->record('Vendor_Z', 'Guide', 'z.md', 'Zulu', '', 'invoice'),
            $this->record('Vendor_A', 'Guide', 'a.md', 'Alpha', '', 'invoice'),
            $this->record('Vendor_M', 'Guide', 'm.md', 'Mike', '', 'invoice'),
        ]);

        $this->assertSame(
            ['Zulu', 'Alpha', 'Mike'],
            array_map(static fn (SearchHitInterface $hit): string => $hit->getTitle(), $hits)
        );
    }

    public function testDropsHitsFromSectionsTheAdminMayNotSee(): void
    {
        $this->hidden = ['Vendor_A/Secret'];

        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Secret', 'a.md', 'Hidden Invoice', '', 'invoice'),
            $this->record('Vendor_A', 'Guide', 'b.md', 'Public Page', '', 'invoice'),
        ]);

        $this->assertCount(1, $hits);
        $this->assertSame('Public Page', $hits[0]->getTitle());
    }

    public function testReturnsNothingWhenNothingMatches(): void
    {
        $this->assertSame([], $this->search('zzz', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Setup', 'Intro', 'run the reindex command'),
        ]));
    }

    public function testCarriesEverythingNeededToLinkToTheMatchedPage(): void
    {
        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'advanced/a.md', 'Invoices', '', 'body', 'Vendor A'),
        ]);

        $this->assertSame('Vendor_A', $hits[0]->getModuleName());
        $this->assertSame('Vendor A', $hits[0]->getModuleTitle());
        $this->assertSame('Guide', $hits[0]->getSectionName());
        $this->assertSame('advanced/a.md', $hits[0]->getRelativePath());
        $this->assertSame('Vendor A > Guide > Invoices', $hits[0]->getBreadcrumb());
    }

    public function testReturnsAPlainTextSnippetAroundTheMatch(): void
    {
        $body = str_repeat('a ', 100) . 'the **invoice** total' . str_repeat(' b', 100);

        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', $body),
        ]);

        $this->assertStringContainsString('invoice', $hits[0]->getSnippet());
        $this->assertStringNotContainsString('<', $hits[0]->getSnippet());
        $this->assertLessThan(mb_strlen($body), mb_strlen($hits[0]->getSnippet()));
    }

    public function testCachesTheBuiltIndexUnderTheSearchIndexKey(): void
    {
        $this->cache->expects($this->once())
            ->method('save')
            ->with($this->isType('string'), 'magebit_documentation_search_index');

        $this->search('invoice', [$this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', 'invoice')]);
    }

    public function testTagsTheCachedIndexWithTheConfigCacheItIsBuiltFrom(): void
    {
        $this->cache->expects($this->once())
            ->method('save')
            ->with($this->isType('string'), 'magebit_documentation_search_index', [ConfigCacheType::CACHE_TAG]);

        $this->search('invoice', [$this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', 'invoice')]);
    }

    public function testServesTheCachedIndexWithoutRebuildingIt(): void
    {
        $this->cached = (string)(new Json())->serialize([
            $this->record('Vendor_A', 'Guide', 'a.md', 'Cached Page', '', 'invoice'),
        ]);
        $this->indexer->expects($this->never())->method('build');

        $hits = $this->index->search('invoice');

        $this->assertCount(1, $hits);
        $this->assertSame('Cached Page', $hits[0]->getTitle());
    }

    public function testRebuildsWhenTheCachedPayloadIsNotReadable(): void
    {
        $this->cached = 'not json at all';

        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Rebuilt Page', '', 'invoice'),
        ]);

        $this->assertCount(1, $hits);
        $this->assertSame('Rebuilt Page', $hits[0]->getTitle());
    }

    public function testRebuildsWhenACachedRecordHasTheWrongShape(): void
    {
        $record = $this->record('Vendor_A', 'Guide', 'a.md', 'Cached Page', '', 'invoice');
        unset($record['body']);
        $this->cached = (string)(new Json())->serialize([$record]);

        $hits = $this->search('invoice', [
            $this->record('Vendor_A', 'Guide', 'a.md', 'Rebuilt Page', '', 'invoice'),
        ]);

        $this->assertCount(1, $hits);
        $this->assertSame('Rebuilt Page', $hits[0]->getTitle());
    }

    public function testBuildsTheIndexOnlyOncePerRequest(): void
    {
        $records = [$this->record('Vendor_A', 'Guide', 'a.md', 'Setup', '', 'invoice')];
        $this->indexer->expects($this->once())->method('build')->willReturn($records);

        $this->index->search('invoice');
        $this->index->search('invoice');
    }

    /**
     * Run a search against a stubbed index.
     *
     * @param string $query
     * @param list<SearchRecord> $records
     * @param int $limit
     * @return list<SearchHitInterface>
     */
    private function search(string $query, array $records, int $limit = 20): array
    {
        $this->indexer->method('build')->willReturn($records);

        return $this->index->search($query, $limit);
    }

    /**
     * @param string $module
     * @param string $section
     * @param string $path
     * @param string $title
     * @param string $headings
     * @param string $body
     * @param string $moduleTitle
     * @return SearchRecord
     */
    private function record(
        string $module,
        string $section,
        string $path,
        string $title,
        string $headings,
        string $body,
        string $moduleTitle = 'Vendor A'
    ): array {
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

    /**
     * @param string $name
     * @return SectionInterface
     */
    private function section(string $name): SectionInterface
    {
        return new Section($name, 'Vendor_A::Docs', null, 10, new Category('', 100, [], []), false);
    }
}
