<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\DocumentationTree;
use Magebit\Documentation\Model\Tree\AclFilter;
use Magebit\Documentation\Model\Tree\Builder;
use Magebit\Documentation\Model\Tree\Storage;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DocumentationTreeTest extends TestCase
{
    /**
     * @var Builder&MockObject
     */
    private Builder $builder;

    /**
     * @var Storage&MockObject
     */
    private Storage $storage;

    /**
     * @var AuthorizationInterface&MockObject
     */
    private AuthorizationInterface $authorization;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(Builder::class);
        $this->storage = $this->createMock(Storage::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
    }

    public function testCachesTheUnfilteredTreeAndReturnsTheFilteredOne(): void
    {
        $this->storage->method('load')->willReturn(null);
        $this->builder->method('build')->willReturn($this->tree());
        $this->authorization->method('isAllowed')->willReturn(false);

        $savedSections = 0;
        $this->storage->expects($this->once())
            ->method('save')
            ->willReturnCallback(
                /**
                 * @param array<string, ModuleDocsInterface> $tree
                 * @return void
                 */
                function (array $tree) use (&$savedSections): void {
                    $savedSections = count($tree['Vendor_A']->getSections());
                }
            );

        $visible = $this->subject()->get();

        $this->assertSame(2, $savedSections, 'the cached tree must not be ACL filtered');
        $this->assertCount(1, $visible['Vendor_A']->getSections());
        $this->assertSame('Guide', $visible['Vendor_A']->getSections()[0]->getName());
    }

    public function testUsesTheCachedTreeWithoutRebuildingIt(): void
    {
        $this->storage->method('load')->willReturn($this->tree());
        $this->builder->expects($this->never())->method('build');
        $this->storage->expects($this->never())->method('save');
        $this->authorization->method('isAllowed')->willReturn(true);

        $this->assertCount(1, $this->subject()->get());
    }

    public function testReadsTheTreeOnlyOncePerRequest(): void
    {
        $this->storage->expects($this->once())->method('load')->willReturn($this->tree());
        $this->authorization->method('isAllowed')->willReturn(true);

        $tree = $this->subject();
        $tree->get();
        $tree->get();
    }

    public function testFindsASectionByModuleAndName(): void
    {
        $this->storage->method('load')->willReturn($this->tree());
        $this->authorization->method('isAllowed')->willReturn(true);

        $tree = $this->subject();

        $this->assertSame('Secret', $tree->getSection('Vendor_A', 'Secret')?->getName());
        $this->assertNull($tree->getSection('Vendor_A', 'Missing'));
        $this->assertNull($tree->getSection('Vendor_B', 'Guide'));
    }

    public function testHidesASectionTheAdminMayNotSee(): void
    {
        $this->storage->method('load')->willReturn($this->tree());
        $this->authorization->method('isAllowed')->willReturn(false);

        $this->assertNull($this->subject()->getSection('Vendor_A', 'Secret'));
    }

    public function testFindsTheFirstPageOfTheFirstSection(): void
    {
        $this->storage->method('load')->willReturn($this->tree());
        $this->authorization->method('isAllowed')->willReturn(true);

        $first = $this->subject()->getFirst();

        $this->assertNotNull($first);
        $this->assertSame('Vendor_A', $first['module']->getModuleName());
        $this->assertSame('Guide', $first['section']->getName());
        $this->assertSame('nested/a.md', $first['page']->getRelativePath());
    }

    public function testReturnsNoFirstPageWhenNothingIsVisible(): void
    {
        $this->storage->method('load')->willReturn($this->lockedTree());
        $this->authorization->method('isAllowed')->willReturn(false);

        $this->assertNull($this->subject()->getFirst());
    }

    /**
     * A module whose first section only holds pages inside a sub-category.
     *
     * @return array<string, ModuleDocsInterface>
     */
    private function tree(): array
    {
        $nested = new Category('Nested', 10, [new Page('nested/a.md', 'a.md', 'A', 10, false)], []);
        $guide = new Section('Guide', 'Docs', null, 10, new Category('', 100, [], [$nested]), false);
        $secret = new Section(
            'Secret',
            'Secret',
            'Vendor_A::secret',
            20,
            new Category('', 100, [new Page('b.md', 'b.md', 'B', 10, false)], []),
            false
        );

        return ['Vendor_A' => new ModuleDocs('Vendor_A', 'A', null, 10, [$guide, $secret])];
    }

    /**
     * A module whose only section needs an ACL resource.
     *
     * @return array<string, ModuleDocsInterface>
     */
    private function lockedTree(): array
    {
        $sections = $this->tree()['Vendor_A']->getSections();

        return ['Vendor_A' => new ModuleDocs('Vendor_A', 'A', null, 10, [$sections[1]])];
    }

    /**
     * Build the subject once the test has set its expectations.
     *
     * @return DocumentationTree
     */
    private function subject(): DocumentationTree
    {
        return new DocumentationTree($this->builder, $this->storage, new AclFilter($this->authorization));
    }
}
