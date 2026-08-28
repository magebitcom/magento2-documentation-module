<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Search;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\PageRepositoryInterface;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\Search\Indexer;
use Magebit\Documentation\Model\Tree\Builder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IndexerTest extends TestCase
{
    /**
     * @var Builder&MockObject
     */
    private Builder $builder;

    /**
     * @var PageRepositoryInterface&MockObject
     */
    private PageRepositoryInterface $pages;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var Indexer
     */
    private Indexer $indexer;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(Builder::class);
        $this->pages = $this->createMock(PageRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->indexer = new Indexer($this->builder, $this->pages, $this->logger);
    }

    public function testBuildsOneRecordForEveryPageIncludingNestedCategories(): void
    {
        $nested = new Category('Advanced', 20, [new Page('advanced/api.md', 'api.md', 'Api', 10, false)], []);
        $root = new Category('', 100, [new Page('index.md', 'index.md', 'Overview', 1, true)], [$nested]);
        $this->givenTree(['Vendor_A' => $this->module('Vendor_A', 'Vendor A', [$this->section('Guide', $root)])]);
        $this->pages->method('getContentForSection')->willReturn('body text');

        $records = $this->indexer->build();

        $this->assertCount(2, $records);
        $this->assertSame('Vendor_A', $records[0]['module']);
        $this->assertSame('Vendor A', $records[0]['moduleTitle']);
        $this->assertSame('Guide', $records[0]['section']);
        $this->assertSame('index.md', $records[0]['path']);
        $this->assertSame('Overview', $records[0]['title']);
        $this->assertSame('advanced/api.md', $records[1]['path']);
        $this->assertSame('Api', $records[1]['title']);
    }

    public function testReadsPagesThroughTheSectionBypassRatherThanTheAclFilteredTree(): void
    {
        $section = $this->section('Guide', $this->rootWith(new Page('index.md', 'index.md', 'Overview', 1, true)));
        $this->givenTree(['Vendor_A' => $this->module('Vendor_A', 'Vendor A', [$section])]);

        $this->pages->expects($this->once())
            ->method('getContentForSection')
            ->with('Vendor_A', $this->identicalTo($section), 'index.md')
            ->willReturn('body text');
        $this->pages->expects($this->never())->method('getContent');

        $this->indexer->build();
    }

    public function testCollectsHeadingsSeparatelyFromTheBody(): void
    {
        $content = "# Title One\n\nsome prose\n\n### Deep Heading\n\nmore prose\nnot a # heading\n";

        $record = $this->buildOne($content);

        $this->assertStringContainsString('Title One', $record['headings']);
        $this->assertStringContainsString('Deep Heading', $record['headings']);
        $this->assertStringNotContainsString('some prose', $record['headings']);
        $this->assertStringNotContainsString('not a # heading', $record['headings']);
    }

    public function testDropsFrontMatterFromTheBody(): void
    {
        $record = $this->buildOne("---\ntitle: Invoices\norder: 5\n---\n\nreal prose here\n");

        $this->assertSame('real prose here', $record['body']);
    }

    public function testDropsFrontMatterBehindAByteOrderMarkAndWindowsLineEndings(): void
    {
        $record = $this->buildOne("\xEF\xBB\xBF---\r\ntitle: Invoices\r\n---\r\n\r\nreal prose here\r\n");

        $this->assertStringNotContainsString('title', $record['body']);
        $this->assertStringContainsString('real prose here', $record['body']);
    }

    public function testKeepsAThematicBreakThatOnlyLooksLikeFrontMatter(): void
    {
        $record = $this->buildOne("intro\n\n---\nnot front matter\n---\n\ntail");

        $this->assertStringContainsString('not front matter', $record['body']);
        $this->assertStringContainsString('intro', $record['body']);
    }

    public function testDoesNotTreatACommentInsideAFenceAsAHeading(): void
    {
        $record = $this->buildOne("```\n# not a real heading\n```\n\n## Real Heading\n");

        $this->assertStringNotContainsString('not a real heading', $record['headings']);
        $this->assertStringContainsString('Real Heading', $record['headings']);
    }

    public function testDropsLinkUrlsFromHeadingsAsWellAsFromTheBody(): void
    {
        $record = $this->buildOne("## See [the docs](https://example.com/docs)\n");

        $this->assertSame('See the docs', $record['headings']);
        $this->assertStringNotContainsString('http', $record['headings']);
    }

    public function testDropsEmphasisMarkersButKeepsTheWordsTheyWrap(): void
    {
        $record = $this->buildOne('the **invoice** total is ~~never~~ *rounded*');

        $this->assertSame('the invoice total is never rounded', $record['body']);
    }

    public function testStripsFencedCodeBlocksFromTheBody(): void
    {
        $content = "before\n\n```\nrun `hiddencommand` now\n```\n\nafter\n";

        $record = $this->buildOne($content);

        $this->assertStringNotContainsString('hiddencommand', $record['body']);
        $this->assertStringNotContainsString('`', $record['body']);
        $this->assertStringContainsString('before', $record['body']);
        $this->assertStringContainsString('after', $record['body']);
    }

    public function testStripsTildeFencedCodeBlocksFromTheBody(): void
    {
        $content = "before\n\n~~~\nhiddensample\n~~~\n\nafter\n";

        $record = $this->buildOne($content);

        $this->assertStringNotContainsString('hiddensample', $record['body']);
        $this->assertStringContainsString('after', $record['body']);
    }

    public function testStripsInlineCodeFromTheBody(): void
    {
        $record = $this->buildOne('run `bin/magento setup:upgrade` afterwards');

        $this->assertStringNotContainsString('setup:upgrade', $record['body']);
        $this->assertStringContainsString('afterwards', $record['body']);
    }

    public function testKeepsLinkTextButDropsTheLinkUrl(): void
    {
        $record = $this->buildOne('see the [invoice guide](../invoices.md) for more');

        $this->assertSame('see the invoice guide for more', $record['body']);
    }

    public function testKeepsLinkTextButDropsAnExternalLinkUrl(): void
    {
        $record = $this->buildOne('see the [invoice guide](https://example.com/invoices) for more');

        $this->assertSame('see the invoice guide for more', $record['body']);
    }

    public function testDropsImagesFromTheBody(): void
    {
        $record = $this->buildOne("![a screenshot](img/shot.png)\n\nkeep this");

        $this->assertStringNotContainsString('img/shot.png', $record['body']);
        $this->assertStringNotContainsString('a screenshot', $record['body']);
        $this->assertStringContainsString('keep this', $record['body']);
    }

    public function testDropsBareAndAutoLinkedUrlsFromTheBody(): void
    {
        $record = $this->buildOne("plain https://example.com/page tail\n\nand <https://example.com/auto> too");

        $this->assertStringNotContainsString('http', $record['body']);
        $this->assertStringNotContainsString('example.com', $record['body']);
        $this->assertStringNotContainsString('<', $record['body']);
        $this->assertStringContainsString('tail', $record['body']);
        $this->assertStringContainsString('too', $record['body']);
    }

    public function testIndexesAChangelogPageLikeAnyOtherPage(): void
    {
        $page = new Page('CHANGELOG.md', 'CHANGELOG.md', 'Changelog', 100, true);
        $section = new Section('Changelog', 'CHANGELOG.md', null, 1000, $this->rootWith($page), true);
        $this->givenTree(['Vendor_A' => $this->module('Vendor_A', 'Vendor A', [$section])]);
        $this->pages->expects($this->once())
            ->method('getContentForSection')
            ->with('Vendor_A', $this->identicalTo($section), 'CHANGELOG.md')
            ->willReturn('## 1.0.0 released');

        $records = $this->indexer->build();

        $this->assertCount(1, $records);
        $this->assertSame('Changelog', $records[0]['title']);
        $this->assertStringContainsString('1.0.0 released', $records[0]['headings']);
    }

    public function testStillIndexesTheTitleOfAPageThatCannotBeRead(): void
    {
        $this->givenTree([
            'Vendor_A' => $this->module('Vendor_A', 'Vendor A', [
                $this->section('Guide', $this->rootWith(new Page('gone.md', 'gone.md', 'Gone', 1, false))),
            ]),
        ]);
        $this->pages->method('getContentForSection')->willReturn(null);

        $records = $this->indexer->build();

        $this->assertCount(1, $records);
        $this->assertSame('Gone', $records[0]['title']);
        $this->assertSame('', $records[0]['body']);
        $this->assertSame('', $records[0]['headings']);
    }

    public function testWalksModulesAndSectionsInTreeOrder(): void
    {
        $this->givenTree([
            'Vendor_B' => $this->module('Vendor_B', 'Vendor B', [
                $this->section('Zulu', $this->rootWith(new Page('z.md', 'z.md', 'Zulu Page', 1, false))),
                $this->section('Alpha', $this->rootWith(new Page('a.md', 'a.md', 'Alpha Page', 1, false))),
            ]),
            'Vendor_A' => $this->module('Vendor_A', 'Vendor A', [
                $this->section('Guide', $this->rootWith(new Page('g.md', 'g.md', 'Guide Page', 1, false))),
            ]),
        ]);
        $this->pages->method('getContentForSection')->willReturn('body');

        $titles = array_map(static fn (array $r): string => $r['title'], $this->indexer->build());

        $this->assertSame(['Zulu Page', 'Alpha Page', 'Guide Page'], $titles);
    }

    public function testLogsHowManyPagesWereIndexed(): void
    {
        $this->givenTree([
            'Vendor_A' => $this->module('Vendor_A', 'Vendor A', [
                $this->section('Guide', new Category('', 100, [
                    new Page('a.md', 'a.md', 'A', 1, false),
                    new Page('b.md', 'b.md', 'B', 2, false),
                ], [])),
            ]),
        ]);
        $this->pages->method('getContentForSection')->willReturn('body');

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->isType('string'), ['pages' => 2]);

        $this->indexer->build();
    }

    /**
     * Build a single-page tree from one markdown file and return its record.
     *
     * @param string $content
     * @return array{module: string, moduleTitle: string, section: string, path: string, title: string,
     *     headings: string, body: string}
     */
    private function buildOne(string $content): array
    {
        $this->givenTree([
            'Vendor_A' => $this->module('Vendor_A', 'Vendor A', [
                $this->section('Guide', $this->rootWith(new Page('a.md', 'a.md', 'A', 1, false))),
            ]),
        ]);
        $this->pages->method('getContentForSection')->willReturn($content);

        $records = $this->indexer->build();

        return $records[0];
    }

    /**
     * @param array<string, ModuleDocsInterface> $tree
     * @return void
     */
    private function givenTree(array $tree): void
    {
        $this->builder->method('build')->willReturn($tree);
    }

    /**
     * @param string $name
     * @param string $title
     * @param list<SectionInterface> $sections
     * @return ModuleDocsInterface
     */
    private function module(string $name, string $title, array $sections): ModuleDocsInterface
    {
        return new ModuleDocs($name, $title, null, 10, $sections);
    }

    /**
     * @param string $name
     * @param Category $root
     * @return SectionInterface
     */
    private function section(string $name, Category $root): SectionInterface
    {
        return new Section($name, 'Vendor_A::Docs', null, 10, $root, false);
    }

    /**
     * @param Page $page
     * @return Category
     */
    private function rootWith(Page $page): Category
    {
        return new Category('', 100, [$page], []);
    }
}
