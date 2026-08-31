<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Markdown;

use Magebit\Documentation\Model\Markdown\UrlBuilder;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UrlBuilderTest extends TestCase
{
    /**
     * @var UrlInterface&MockObject
     */
    private UrlInterface $url;

    /**
     * @var UrlBuilder
     */
    private UrlBuilder $urlBuilder;

    protected function setUp(): void
    {
        $this->url = $this->createMock(UrlInterface::class);
        $this->urlBuilder = new UrlBuilder($this->url);
    }

    public function testPageUsesTheDocumentationRouteAndQueryParameters(): void
    {
        $this->url->expects($this->once())
            ->method('getUrl')
            ->with(
                'magebit_documentation/index/index',
                ['_query' => ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'advanced/other.md']]
            )
            ->willReturn('https://shop.test/admin/doc');

        $this->assertSame(
            'https://shop.test/admin/doc',
            $this->urlBuilder->page('Vendor_A', 'Guide', 'advanced/other.md')
        );
    }

    public function testAssetUsesTheAssetRouteAndQueryParameters(): void
    {
        $this->url->expects($this->once())
            ->method('getUrl')
            ->with(
                'magebit_documentation/asset/index',
                ['_query' => ['module' => 'Vendor_A', 'section' => 'Guide', 'path' => 'img/diagram.png']]
            )
            ->willReturn('https://shop.test/admin/asset');

        $this->assertSame(
            'https://shop.test/admin/asset',
            $this->urlBuilder->asset('Vendor_A', 'Guide', 'img/diagram.png')
        );
    }
}
