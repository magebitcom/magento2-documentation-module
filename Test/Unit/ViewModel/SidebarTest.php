<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\ViewModel;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Model\CurrentPage;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use Magebit\Documentation\ViewModel\Sidebar;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class SidebarTest extends TestCase
{
    public function testExposesTheTreeTheAdminMaySee(): void
    {
        $tree = $this->tree();

        $this->assertSame($tree->get(), $this->sidebar([], $tree)->getTree());
    }

    public function testBuildsThePageUrlOfATreeEntry(): void
    {
        $this->assertSame(
            'https://admin/doc?module=Vendor_A&section=Guide&path=advanced%2Fapi.md',
            $this->sidebar()->getPageUrl('Vendor_A', 'Guide', 'advanced/api.md')
        );
    }

    public function testMarksOnlyTheRequestedPageAsActive(): void
    {
        $sidebar = $this->sidebar(['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'usage.md']);

        $this->assertTrue($sidebar->isActive('Vendor_A', 'Guide', 'usage.md'));
        $this->assertFalse($sidebar->isActive('Vendor_A', 'Guide', 'intro.md'));
        $this->assertFalse($sidebar->isActive('Vendor_A', 'Manual', 'usage.md'));
        $this->assertFalse($sidebar->isActive('Vendor_B', 'Guide', 'usage.md'));
    }

    public function testMarksTheFirstPageAsActiveWhenTheRequestNamesNone(): void
    {
        $sidebar = $this->sidebar();

        $this->assertTrue($sidebar->isActive('Vendor_A', 'Guide', 'intro.md'));
        $this->assertFalse($sidebar->isActive('Vendor_A', 'Guide', 'usage.md'));
    }

    public function testReportsTheCurrentPage(): void
    {
        $current = $this->sidebar(['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'usage.md'])->getCurrent();

        $this->assertSame(['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'usage.md'], $current);
    }

    /**
     * @param array<string, string> $params
     * @param DocumentationTreeInterface|null $tree
     * @return Sidebar
     */
    private function sidebar(array $params = [], ?DocumentationTreeInterface $tree = null): Sidebar
    {
        $tree ??= $this->tree();

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $params[$key] ?? $default
        );

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            function (string $route, ?array $params = null): string {
                $query = $params['_query'] ?? [];

                return 'https://admin/doc?' . http_build_query(is_array($query) ? $query : []);
            }
        );

        return new Sidebar($tree, new CurrentPage($request, $tree), new UrlBuilder($url));
    }

    /**
     * @return DocumentationTreeInterface
     */
    private function tree(): DocumentationTreeInterface
    {
        $intro = new Page('intro.md', 'intro.md', 'Intro', 10, true);
        $usage = new Page('usage.md', 'usage.md', 'Usage', 20, false);
        $section = new Section('Guide', 'Vendor_A::Docs', null, 10, new Category('', 0, [$intro, $usage], []), false);
        $module = new ModuleDocs('Vendor_A', 'Vendor A', null, 10, [$section]);

        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('get')->willReturn(['Vendor_A' => $module]);
        $tree->method('getFirst')->willReturn(['module' => $module, 'section' => $section, 'page' => $intro]);

        return $tree;
    }
}
