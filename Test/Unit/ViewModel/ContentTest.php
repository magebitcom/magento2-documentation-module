<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\ViewModel;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Api\MarkdownRendererInterface;
use Magebit\Documentation\Api\PageRepositoryInterface;
use Magebit\Documentation\Api\SyntaxHighlighterInterface;
use Magebit\Documentation\Model\CurrentPage;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use Magebit\Documentation\ViewModel\Content;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class ContentTest extends TestCase
{
    private const AT_USAGE = ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'usage.md'];

    public function testRendersTheRequestedPageAndDecoratesIt(): void
    {
        $pages = $this->createMock(PageRepositoryInterface::class);
        $pages->expects($this->once())
            ->method('getContent')
            ->with('Vendor_A', 'Guide', 'usage.md')
            ->willReturn('# Usage');

        $renderer = $this->createMock(MarkdownRendererInterface::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with('# Usage', self::AT_USAGE)
            ->willReturn('<h1>Usage</h1>');

        $highlighter = $this->createMock(SyntaxHighlighterInterface::class);
        $highlighter->method('decorate')->willReturnCallback(
            static fn (string $html): string => '<decorated>' . $html . '</decorated>'
        );

        $content = $this->content(self::AT_USAGE, pages: $pages, renderer: $renderer, highlighter: $highlighter);

        $this->assertSame('<decorated><h1>Usage</h1></decorated>', $content->getRenderedContent());
    }

    public function testRendersTheFirstPageWhenTheRequestNamesNone(): void
    {
        $pages = $this->createMock(PageRepositoryInterface::class);
        $pages->expects($this->once())
            ->method('getContent')
            ->with('Vendor_A', 'Guide', 'intro.md')
            ->willReturn('# Intro');

        $this->assertSame('# Intro', $this->content([], pages: $pages)->getRenderedContent());
    }

    public function testRendersNothingWhenThePageCannotBeRead(): void
    {
        $pages = $this->createMock(PageRepositoryInterface::class);
        $pages->method('getContent')->willReturn(null);

        $renderer = $this->createMock(MarkdownRendererInterface::class);
        $renderer->expects($this->never())->method('render');

        $this->assertSame('', $this->content(self::AT_USAGE, pages: $pages, renderer: $renderer)->getRenderedContent());
    }

    public function testRendersNothingWhenTheAdminMaySeeNoDocumentation(): void
    {
        $pages = $this->createMock(PageRepositoryInterface::class);
        $pages->expects($this->never())->method('getContent');

        $content = $this->content([], pages: $pages, tree: $this->emptyTree());

        $this->assertSame('', $content->getRenderedContent());
        $this->assertSame([], $content->getBreadcrumb());
        $this->assertNull($content->getPrevious());
        $this->assertNull($content->getNext());
    }

    public function testBreadcrumbNamesTheModuleTheSectionAndThePage(): void
    {
        $this->assertSame(
            ['Vendor A', 'Guide', 'Usage'],
            $this->content(self::AT_USAGE)->getBreadcrumb()
        );
    }

    public function testBreadcrumbStopsAtTheSectionWhenThePageIsUnknown(): void
    {
        $content = $this->content(['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'ghost.md']);

        $this->assertSame(['Vendor A', 'Guide'], $content->getBreadcrumb());
    }

    public function testBreadcrumbIsEmptyForASectionTheAdminMayNotSee(): void
    {
        $this->assertSame(
            [],
            $this->content(['module' => 'Vendor_A', 'section' => 'Hidden', 'path' => 'usage.md'])->getBreadcrumb()
        );
    }

    public function testPreviousAndNextFollowTheOrderOfTheSection(): void
    {
        $content = $this->content(self::AT_USAGE);

        $this->assertSame(
            ['title' => 'Intro', 'url' => 'https://admin/doc?module=Vendor_A&section=Guide&path=intro.md'],
            $content->getPrevious()
        );
        $this->assertSame(
            ['title' => 'Api', 'url' => 'https://admin/doc?module=Vendor_A&section=Guide&path=advanced%2Fapi.md'],
            $content->getNext()
        );
    }

    public function testTheFirstPageHasNoPreviousAndTheLastHasNoNext(): void
    {
        $first = $this->content(['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'intro.md']);
        $last = $this->content(['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'advanced/api.md']);

        $this->assertNull($first->getPrevious());
        $this->assertSame('Usage', $first->getNext()['title'] ?? null);
        $this->assertSame('Usage', $last->getPrevious()['title'] ?? null);
        $this->assertNull($last->getNext());
    }

    public function testThereAreNoNeighboursForAPageOutsideTheSection(): void
    {
        $content = $this->content(['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'ghost.md']);

        $this->assertNull($content->getPrevious());
        $this->assertNull($content->getNext());
    }

    /**
     * @param array<string, string> $params
     * @param PageRepositoryInterface|null $pages
     * @param MarkdownRendererInterface|null $renderer
     * @param SyntaxHighlighterInterface|null $highlighter
     * @param DocumentationTreeInterface|null $tree
     * @return Content
     */
    private function content(
        array $params,
        ?PageRepositoryInterface $pages = null,
        ?MarkdownRendererInterface $renderer = null,
        ?SyntaxHighlighterInterface $highlighter = null,
        ?DocumentationTreeInterface $tree = null
    ): Content {
        $tree ??= $this->tree();

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $params[$key] ?? $default
        );

        if ($pages === null) {
            $pages = $this->createMock(PageRepositoryInterface::class);
            $pages->method('getContent')->willReturn('# Page');
        }

        if ($renderer === null) {
            $renderer = $this->createMock(MarkdownRendererInterface::class);
            $renderer->method('render')->willReturnCallback(static fn (string $markdown): string => $markdown);
        }

        if ($highlighter === null) {
            $highlighter = $this->createMock(SyntaxHighlighterInterface::class);
            $highlighter->method('decorate')->willReturnCallback(static fn (string $html): string => $html);
        }

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            function (string $route, ?array $arguments = null): string {
                $query = $arguments['_query'] ?? [];

                return 'https://admin/doc?' . http_build_query(is_array($query) ? $query : []);
            }
        );

        return new Content(
            new CurrentPage($request, $tree),
            $tree,
            $pages,
            $renderer,
            $highlighter,
            new UrlBuilder($url)
        );
    }

    /**
     * One module with one section holding "intro.md", "usage.md" and "advanced/api.md" in that order.
     *
     * @return DocumentationTreeInterface
     */
    private function tree(): DocumentationTreeInterface
    {
        $intro = new Page('intro.md', 'intro.md', 'Intro', 10, true);
        $usage = new Page('usage.md', 'usage.md', 'Usage', 20, false);
        $api = new Page('advanced/api.md', 'api.md', 'Api', 10, false);
        $advanced = new Category('Advanced', 10, [$api], []);
        $section = new Section(
            'Guide',
            'Vendor_A::Docs',
            null,
            10,
            new Category('', 0, [$intro, $usage], [$advanced]),
            false
        );
        $module = new ModuleDocs('Vendor_A', 'Vendor A', null, 10, [$section]);

        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('get')->willReturn(['Vendor_A' => $module]);
        $tree->method('getFirst')->willReturn(['module' => $module, 'section' => $section, 'page' => $intro]);
        $tree->method('getSection')->willReturnCallback(
            fn (string $module, string $name): ?Section => $module === 'Vendor_A' && $name === 'Guide'
                ? $section
                : null
        );

        return $tree;
    }

    /**
     * @return DocumentationTreeInterface
     */
    private function emptyTree(): DocumentationTreeInterface
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('get')->willReturn([]);
        $tree->method('getFirst')->willReturn(null);
        $tree->method('getSection')->willReturn(null);

        return $tree;
    }
}
