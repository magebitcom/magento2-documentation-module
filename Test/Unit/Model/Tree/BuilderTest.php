<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Tree;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\DirectoryScannerInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Model\Config\Converter;
use Magebit\Documentation\Model\Config\Data as ConfigData;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Tree\Builder;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type DocModule from Converter
 * @phpstan-import-type DocSection from Converter
 */
class BuilderTest extends TestCase
{
    public function testOrdersModulesAndSectionsBySortOrder(): void
    {
        $tree = $this->build([
            'Vendor_B' => $this->moduleConfig('B', 20, [
                $this->sectionConfig('Second', 'Docs/Second', 20),
                $this->sectionConfig('First', 'Docs/First', 10),
            ]),
            'Vendor_A' => $this->moduleConfig('A', 10, [$this->sectionConfig('Guide', 'Docs', 100)]),
        ]);

        $this->assertSame(['Vendor_A', 'Vendor_B'], array_keys($tree));
        $this->assertSame(
            ['First', 'Second'],
            array_map(static fn ($section) => $section->getName(), $tree['Vendor_B']->getSections())
        );
    }

    public function testBreaksSortOrderTiesByTitleAndNameIgnoringLetterCase(): void
    {
        $tree = $this->build([
            'Vendor_B' => $this->moduleConfig('Beta', 10, [
                $this->sectionConfig('Zebra', 'Docs/Z', 100),
                $this->sectionConfig('apple', 'Docs/A', 100),
            ]),
            'Vendor_A' => $this->moduleConfig('alpha', 10, [$this->sectionConfig('Guide', 'Docs', 100)]),
        ]);

        $this->assertSame(['Vendor_A', 'Vendor_B'], array_keys($tree));
        $this->assertSame(
            ['apple', 'Zebra'],
            array_map(static fn ($section) => $section->getName(), $tree['Vendor_B']->getSections())
        );
    }

    public function testDropsSectionsWhosePathDoesNotResolve(): void
    {
        $tree = $this->build(
            ['Vendor_A' => $this->moduleConfig('A', 10, [$this->sectionConfig('Guide', 'Missing', 100)])],
            resolves: false
        );

        $this->assertSame([], $tree);
    }

    public function testDropsSectionsThatScanEmpty(): void
    {
        $tree = $this->build(
            ['Vendor_A' => $this->moduleConfig('A', 10, [$this->sectionConfig('Guide', 'Docs', 100)])],
            empty: true
        );

        $this->assertSame([], $tree);
    }

    public function testFallsBackToTheModuleNameWhenTitleIsBlank(): void
    {
        $tree = $this->build(
            ['Vendor_A' => $this->moduleConfig('', 10, [$this->sectionConfig('Guide', 'Docs', 100)])]
        );

        $this->assertSame('Vendor_A', $tree['Vendor_A']->getTitle());
    }

    public function testBuildsAChangelogFromASingleFileWithoutScanning(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->once())
            ->method('resolveChangelogFile')
            ->with('Vendor_A', 'docs/CHANGELOG.md')
            ->willReturn('/abs/path/docs/CHANGELOG.md');

        $scanner = $this->createMock(DirectoryScannerInterface::class);
        $scanner->expects($this->never())->method('scan');

        $sections = $this->builder(
            ['Vendor_A' => $this->moduleConfig('A', 10, [$this->changelogConfig('Release notes')])],
            $resolver,
            $scanner
        )->build()['Vendor_A']->getSections();

        $this->assertTrue($sections[0]->isChangelog());
        $this->assertSame('docs/CHANGELOG.md', $sections[0]->getPath());

        $pages = $sections[0]->getRoot()->getPages();
        $this->assertCount(1, $pages);
        $this->assertSame('docs/CHANGELOG.md', $pages[0]->getRelativePath());
        $this->assertSame('Release notes', $pages[0]->getTitle());
    }

    public function testDropsAChangelogWhoseFileDoesNotResolve(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveChangelogFile')->willReturn(null);

        $scanner = $this->createMock(DirectoryScannerInterface::class);
        $scanner->expects($this->never())->method('scan');

        $tree = $this->builder(
            ['Vendor_A' => $this->moduleConfig('A', 10, [$this->changelogConfig('Changelog')])],
            $resolver,
            $scanner
        )->build();

        $this->assertSame([], $tree);
    }

    /**
     * @param array<string, DocModule> $config
     * @param bool $resolves
     * @param bool $empty
     * @return array<string, ModuleDocsInterface>
     */
    private function build(array $config, bool $resolves = true, bool $empty = false): array
    {
        $configData = $this->createMock(ConfigData::class);
        $configData->method('get')->willReturn($config);

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn($resolves ? '/abs/path' : null);

        $scanner = $this->createMock(DirectoryScannerInterface::class);
        $scanner->method('scan')->willReturn(
            $empty
                ? new Category('', 100, [], [])
                : new Category('', 100, [new Page('a.md', 'a.md', 'A', 1, false)], [])
        );

        return (new Builder($configData, $resolver, $scanner))->build();
    }

    /**
     * @param array<string, DocModule> $config
     * @param PathResolverInterface $resolver
     * @param DirectoryScannerInterface $scanner
     * @return Builder
     */
    private function builder(
        array $config,
        PathResolverInterface $resolver,
        DirectoryScannerInterface $scanner
    ): Builder {
        $configData = $this->createMock(ConfigData::class);
        $configData->method('get')->willReturn($config);

        return new Builder($configData, $resolver, $scanner);
    }

    /**
     * @param string $title
     * @param int $sortOrder
     * @param list<DocSection> $sections
     * @return DocModule
     */
    private function moduleConfig(string $title, int $sortOrder, array $sections): array
    {
        return ['title' => $title, 'sortOrder' => $sortOrder, 'icon' => null, 'sections' => $sections];
    }

    /**
     * @param string $name
     * @param string $path
     * @param int $sortOrder
     * @return DocSection
     */
    private function sectionConfig(string $name, string $path, int $sortOrder): array
    {
        return [
            'name' => $name,
            'path' => $path,
            'acl' => null,
            'sortOrder' => $sortOrder,
            'isChangelog' => false,
        ];
    }

    /**
     * @param string $name
     * @return DocSection
     */
    private function changelogConfig(string $name): array
    {
        return [
            'name' => $name,
            'path' => 'docs/CHANGELOG.md',
            'acl' => null,
            'sortOrder' => 1000,
            'isChangelog' => true,
        ];
    }
}
