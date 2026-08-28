<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Data;

use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{
    public function testExposesConstructorValues(): void
    {
        $page = new Page('advanced/1-api.md', '1-api.md', 'API Reference', 1, false);

        $this->assertSame('advanced/1-api.md', $page->getRelativePath());
        $this->assertSame('1-api.md', $page->getFileName());
        $this->assertSame('API Reference', $page->getTitle());
        $this->assertSame(1, $page->getSortOrder());
        $this->assertFalse($page->isIndex());
    }

    public function testEmptyCategoryIsReportedEmpty(): void
    {
        $this->assertTrue((new Category('', 100, [], []))->isEmpty());
    }

    public function testCategoryWithOnlyASubCategoryIsNotEmpty(): void
    {
        $child = new Category('advanced', 100, [new Page('a.md', 'a.md', 'A', 1000, false)], []);

        $this->assertFalse((new Category('', 100, [], ['advanced' => $child]))->isEmpty());
    }

    public function testModuleDocsTitleFallsBackToModuleNameWhenEmpty(): void
    {
        $moduleDocs = new ModuleDocs('Vendor_Module', '', null, 100, []);

        $this->assertSame('Vendor_Module', $moduleDocs->getTitle());
    }

    public function testModuleDocsTitleIsReturnedUnchangedWhenSet(): void
    {
        $moduleDocs = new ModuleDocs('Vendor_Module', 'My Module', null, 100, []);

        $this->assertSame('My Module', $moduleDocs->getTitle());
    }
}
