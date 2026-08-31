<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Controller\Adminhtml\Search;

use Magebit\Documentation\Api\SearchIndexInterface;
use Magebit\Documentation\Controller\Adminhtml\Search\Index;
use Magebit\Documentation\Model\Data\SearchHit;
use Magebit\Documentation\Model\Markdown\UrlBuilder;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class IndexTest extends TestCase
{
    /**
     * @var array<array-key, mixed>|null
     */
    private ?array $data = null;

    public function testReturnsTheHitsTheIndexAllowsTheAdminToSee(): void
    {
        $index = $this->createMock(SearchIndexInterface::class);
        $index->method('search')->willReturn(
            [
                new SearchHit('Vendor_A', 'Vendor A', 'Guide', 'intro.md', 'Intro', 'A snippet', 'A > Guide', 60),
            ]
        );

        $this->controller($index)->execute();

        $this->assertSame(
            [
                'success' => true,
                'results' => [
                    [
                        'title' => 'Intro',
                        'snippet' => 'A snippet',
                        'breadcrumb' => 'A > Guide',
                        'url' => 'https://admin/doc?module=Vendor_A&section=Guide&path=intro.md',
                    ],
                ],
                'count' => 1,
            ],
            $this->data
        );
    }

    public function testSearchesForTheQueryParameterAndNothingElse(): void
    {
        $index = $this->createMock(SearchIndexInterface::class);
        $index->expects($this->once())->method('search')->with('order grid')->willReturn([]);

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getParam')->with('q', '')->willReturn('order grid');

        $this->controller($index, $request)->execute();

        $this->assertSame(['success' => true, 'results' => [], 'count' => 0], $this->data);
    }

    public function testHidesTheFailureDetailWhenTheIndexThrows(): void
    {
        $index = $this->createMock(SearchIndexInterface::class);
        $index->method('search')->willThrowException(new RuntimeException('index cache is corrupt at /srv/var'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->controller($index, null, $logger)->execute();

        $this->assertIsArray($this->data);
        $this->assertFalse($this->data['success']);
        $message = $this->data['message'] ?? null;
        $this->assertInstanceOf(Phrase::class, $message);
        $this->assertSame('Search is temporarily unavailable.', $message->render());
        $this->assertStringNotContainsString('corrupt', json_encode($this->data) ?: '');
    }

    public function testHidesTheFailureDetailWhenTheIndexRaisesAnError(): void
    {
        $index = $this->createMock(SearchIndexInterface::class);
        $index->method('search')->willThrowException(new \TypeError('bad argument in Index::search()'));

        $this->controller($index)->execute();

        $this->assertIsArray($this->data);
        $this->assertFalse($this->data['success']);
        $this->assertStringNotContainsString('TypeError', json_encode($this->data) ?: '');
    }

    /**
     * @param SearchIndexInterface $index
     * @param RequestInterface|null $request
     * @param LoggerInterface|null $logger
     * @return Index
     */
    private function controller(
        SearchIndexInterface $index,
        ?RequestInterface $request = null,
        ?LoggerInterface $logger = null
    ): Index {
        if ($request === null) {
            $request = $this->createMock(RequestInterface::class);
            $request->method('getParam')->willReturn('anything');
        }

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(
            function ($data) use ($json): Json {
                $this->data = is_array($data) ? $data : null;

                return $json;
            }
        );

        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            function (string $route, ?array $params = null): string {
                $query = $params['_query'] ?? [];

                return 'https://admin/doc?' . http_build_query(is_array($query) ? $query : []);
            }
        );

        return new Index(
            $context,
            $jsonFactory,
            $index,
            new UrlBuilder($url),
            $logger ?? $this->createMock(LoggerInterface::class)
        );
    }
}
