<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Model\CurrentPage;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;

class CurrentPageTest extends TestCase
{
    public function testUsesTheCoordinatesTheRequestCarries(): void
    {
        $current = $this->currentPage(
            ['module' => 'Vendor_B', 'section' => 'Manual', 'path' => 'setup/install.md']
        )->get();

        $this->assertSame(
            ['module' => 'Vendor_B', 'section' => 'Manual', 'path' => 'setup/install.md'],
            $current
        );
    }

    /**
     * @param array<string, string> $params
     * @return void
     * @dataProvider incompleteParamsProvider
     */
    public function testFallsBackToTheFirstPageTheAdminMaySee(array $params): void
    {
        $this->assertSame(
            ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'intro.md'],
            $this->currentPage($params)->get()
        );
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public function incompleteParamsProvider(): array
    {
        return [
            'nothing asked for' => [[]],
            'no module' => [['section' => 'Manual', 'path' => 'setup/install.md']],
            'no section' => [['module' => 'Vendor_B', 'path' => 'setup/install.md']],
            'no path' => [['module' => 'Vendor_B', 'section' => 'Manual']],
        ];
    }

    public function testKnowsNothingWhenTheAdminMaySeeNoDocumentation(): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('getFirst')->willReturn(null);

        $this->assertNull($this->currentPage([], $tree)->get());
    }

    public function testLeavesTheRequestedPathExactlyAsItArrived(): void
    {
        $current = $this->currentPage(
            ['module' => 'Vendor_B', 'section' => 'Manual', 'path' => '%2e%2e%2fsecret.md']
        )->get();

        $this->assertIsArray($current);
        $this->assertSame('%2e%2e%2fsecret.md', $current['path']);
    }

    /**
     * @param array<string, string> $params
     * @param DocumentationTreeInterface|null $tree
     * @return CurrentPage
     */
    private function currentPage(array $params, ?DocumentationTreeInterface $tree = null): CurrentPage
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $params[$key] ?? $default
        );

        return new CurrentPage($request, $tree ?? $this->tree());
    }

    /**
     * @return DocumentationTreeInterface
     */
    private function tree(): DocumentationTreeInterface
    {
        $page = new Page('intro.md', 'intro.md', 'Intro', 10, true);
        $section = new Section('Guide', 'Vendor_A::Docs', null, 10, new Category('', 0, [$page], []), false);
        $module = new ModuleDocs('Vendor_A', 'Vendor A', null, 10, [$section]);

        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('getFirst')->willReturn(['module' => $module, 'section' => $section, 'page' => $page]);

        return $tree;
    }
}
