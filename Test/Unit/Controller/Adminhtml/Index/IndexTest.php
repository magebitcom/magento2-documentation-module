<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Controller\Adminhtml\Index;

use Magebit\Documentation\Controller\Adminhtml\Index\Index;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page as BackendPage;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    /**
     * A regression test: injecting the backend PageFactory here instead of the framework one
     * has already once produced a page that 500s, because setActiveMenu needs the layout
     * handles the framework factory adds.
     *
     * @return void
     */
    public function testBuildsABackendPageWithTheDocumentationMenuActive(): void
    {
        $title = $this->createMock(Title::class);
        $title->expects($this->once())->method('prepend')->with('Documentation');

        $config = $this->createMock(Config::class);
        $config->method('getTitle')->willReturn($title);

        $page = $this->createMock(BackendPage::class);
        $page->expects($this->once())->method('setActiveMenu')->with(Index::ADMIN_RESOURCE);
        $page->method('getConfig')->willReturn($config);

        $pageFactory = $this->createMock(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $context = $this->createMock(Context::class);

        $result = (new Index($context, $pageFactory))->execute();

        $this->assertSame($page, $result);
    }
}
