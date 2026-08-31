<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Console\Command;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Console\Command\ListDocumentation;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\Tree\Builder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ListDocumentationTest extends TestCase
{
    public function testShowsTheModuleTitleSectionAndResolvedPath(): void
    {
        $tester = $this->runCommand([
            'Vendor_A' => $this->moduleDocs([$this->section('Guide', 'Docs', $this->root())]),
        ]);

        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Vendor_A', $display);
        $this->assertStringContainsString('Module A', $display);
        $this->assertStringContainsString('Guide', $display);
        $this->assertStringContainsString('/abs/path', $display);
    }

    public function testCountsThePagesOfEverySubCategoryAsWell(): void
    {
        $root = new Category('', 100, [$this->page('a.md')], [
            new Category('Deep', 100, [$this->page('b.md'), $this->page('c.md')], [
                new Category('Deeper', 100, [$this->page('d.md')], []),
            ]),
        ]);

        $tester = $this->runCommand([
            'Vendor_A' => $this->moduleDocs([$this->section('Guide', 'Docs', $root)]),
        ]);

        $this->assertMatchesRegularExpression('/Guide.*\b4\b/', $tester->getDisplay());
    }

    public function testResolvesAChangelogSectionAsAFile(): void
    {
        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->once())
            ->method('resolveChangelogFile')
            ->with('Vendor_A', 'CHANGELOG.md')
            ->willReturn(['path' => '/abs/CHANGELOG.md', 'fileName' => 'CHANGELOG.md']);

        $tester = $this->runCommand(
            ['Vendor_A' => $this->moduleDocs([
                $this->section('Changelog', 'CHANGELOG.md', $this->root(), true),
            ])],
            $resolver
        );

        $this->assertStringContainsString('/abs/CHANGELOG.md', $tester->getDisplay());
    }

    public function testSaysSoWhenNoDocumentationIsRegistered(): void
    {
        $tester = $this->runCommand([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No documentation is registered', $tester->getDisplay());
    }

    /**
     * @param array<string, ModuleDocsInterface> $tree
     * @param PathResolverInterface|null $resolver
     * @return CommandTester
     */
    private function runCommand(array $tree, ?PathResolverInterface $resolver = null): CommandTester
    {
        $builder = $this->createMock(Builder::class);
        $builder->method('build')->willReturn($tree);

        if ($resolver === null) {
            $resolver = $this->createMock(PathResolverInterface::class);
            $resolver->method('resolveSectionRoot')->willReturn('/abs/path');
        }

        $tester = new CommandTester(new ListDocumentation($builder, $resolver));
        $tester->execute([]);

        return $tester;
    }

    /**
     * @param list<Section> $sections
     * @return ModuleDocs
     */
    private function moduleDocs(array $sections): ModuleDocs
    {
        return new ModuleDocs('Vendor_A', 'Module A', null, 10, $sections);
    }

    /**
     * @param string $name
     * @param string $path
     * @param Category $root
     * @param bool $isChangelog
     * @return Section
     */
    private function section(string $name, string $path, Category $root, bool $isChangelog = false): Section
    {
        return new Section($name, $path, null, 100, $root, $isChangelog);
    }

    /**
     * @return Category
     */
    private function root(): Category
    {
        return new Category('', 100, [$this->page('a.md')], []);
    }

    /**
     * @param string $fileName
     * @return Page
     */
    private function page(string $fileName): Page
    {
        return new Page($fileName, $fileName, $fileName, 100, false);
    }
}
