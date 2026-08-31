<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Tree;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\Tree\AclFilter;
use Magento\Framework\AuthorizationInterface;
use PHPUnit\Framework\TestCase;

class AclFilterTest extends TestCase
{
    public function testKeepsSectionsWithoutAnAclRequirement(): void
    {
        $tree = $this->filter(null, allowed: false);

        $this->assertCount(1, $tree);
    }

    public function testDropsSectionsTheAdminMayNotSee(): void
    {
        $this->assertSame([], $this->filter('Vendor_A::secret', allowed: false));
    }

    public function testKeepsSectionsTheAdminMaySee(): void
    {
        $this->assertCount(1, $this->filter('Vendor_A::secret', allowed: true));
    }

    public function testChecksTheAclResourceTheSectionAsksFor(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects($this->once())
            ->method('isAllowed')
            ->with('Vendor_A::secret')
            ->willReturn(true);

        $module = new ModuleDocs('Vendor_A', 'A', null, 10, [$this->section('Guide', 'Vendor_A::secret')]);

        $this->assertCount(1, (new AclFilter($authorization))->filter(['Vendor_A' => $module]));
    }

    public function testAsksForNoResourceWhenTheSectionHasNoAcl(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects($this->never())->method('isAllowed');

        $module = new ModuleDocs('Vendor_A', 'A', null, 10, [$this->section('Guide', null)]);

        $this->assertCount(1, (new AclFilter($authorization))->filter(['Vendor_A' => $module]));
    }

    public function testKeepsOnlyThePermittedSectionsOfAModule(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            static fn ($resource) => $resource === 'Vendor_A::allowed'
        );

        $module = new ModuleDocs('Vendor_A', 'A', null, 10, [
            $this->section('Secret', 'Vendor_A::denied'),
            $this->section('Guide', 'Vendor_A::allowed'),
            $this->section('Public', null),
        ]);

        $sections = (new AclFilter($authorization))->filter(['Vendor_A' => $module])['Vendor_A']->getSections();

        $this->assertSame(
            ['Guide', 'Public'],
            array_map(static fn ($section) => $section->getName(), $sections)
        );
    }

    public function testKeepsTheModuleMetadata(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(true);

        $module = new ModuleDocs('Vendor_A', 'A', 'Vendor_A::images/i.svg', 10, [
            $this->section('Guide', 'Vendor_A::secret'),
        ]);

        $filtered = (new AclFilter($authorization))->filter(['Vendor_A' => $module])['Vendor_A'];

        $this->assertSame('Vendor_A', $filtered->getModuleName());
        $this->assertSame('A', $filtered->getTitle());
        $this->assertSame('Vendor_A::images/i.svg', $filtered->getIcon());
        $this->assertSame(10, $filtered->getSortOrder());
    }

    /**
     * @param string|null $acl
     * @param bool $allowed
     * @return array<string, ModuleDocsInterface>
     */
    private function filter(?string $acl, bool $allowed): array
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($allowed);

        $root = new Category('', 100, [new Page('a.md', 'a.md', 'A', 1, false)], []);
        $section = new Section('Guide', 'Docs', $acl, 100, $root, false);
        $module = new ModuleDocs('Vendor_A', 'A', null, 10, [$section]);

        return (new AclFilter($authorization))->filter(['Vendor_A' => $module]);
    }

    /**
     * @param string $name
     * @param string|null $acl
     * @return Section
     */
    private function section(string $name, ?string $acl): Section
    {
        $root = new Category('', 100, [new Page('a.md', 'a.md', 'A', 1, false)], []);

        return new Section($name, 'Docs', $acl, 100, $root, false);
    }
}
