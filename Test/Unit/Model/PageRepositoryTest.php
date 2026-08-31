<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\Section;
use Magebit\Documentation\Model\PageRepository;
use Magebit\Documentation\Model\Scanner\FrontMatterReader;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Parser;

class PageRepositoryTest extends TestCase
{
    private const INTRO = "---\ntitle: Intro Page\n---\n# Intro\n";

    private const PLAIN = "# Plain\n";

    private const CHANGELOG = "---\ntitle: Release Notes\n---\n# 1.0.0\n";

    /**
     * @var string
     */
    private string $base;

    /**
     * @var string
     */
    private string $sectionRoot;

    /**
     * @var string
     */
    private string $changelogRoot;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->base = (string)realpath(sys_get_temp_dir()) . '/magedoc-repo-' . uniqid('', true);
        $this->sectionRoot = $this->base . '/Docs';
        $this->changelogRoot = $this->base . '/changelog';

        mkdir($this->sectionRoot, 0777, true);
        mkdir($this->changelogRoot, 0777, true);
        file_put_contents($this->sectionRoot . '/intro.md', self::INTRO);
        file_put_contents($this->sectionRoot . '/plain.md', self::PLAIN);
        file_put_contents($this->changelogRoot . '/CHANGELOG.md', self::CHANGELOG);

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        (new File())->deleteDirectory($this->base);
    }

    public function testReturnsTheContentOfAPageInTheTree(): void
    {
        $tree = $this->treeWith($this->section());

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolveSectionRoot')
            ->with('Vendor_A', 'Docs')
            ->willReturn($this->sectionRoot);
        $resolver->expects($this->once())
            ->method('resolveFile')
            ->with($this->sectionRoot, 'intro.md', ['md'])
            ->willReturn($this->sectionRoot . '/intro.md');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame(self::INTRO, $repository->getContent('Vendor_A', 'Guide', 'intro.md'));
    }

    public function testReturnsNullWhenTheSectionIsNotInTheAclFilteredTree(): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('getSection')->willReturn(null);

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->never())->method('resolveFile');
        $resolver->expects($this->never())->method('resolveChangelogFile');

        $repository = $this->repository($tree, $resolver);

        $this->assertNull($repository->getContent('Vendor_A', 'Guide', 'intro.md'));
    }

    public function testReturnsNullWhenTheSectionRootDoesNotResolve(): void
    {
        $tree = $this->treeWith($this->section());

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn(null);
        $resolver->expects($this->never())->method('resolveFile');

        $repository = $this->repository($tree, $resolver);

        $this->assertNull($repository->getContent('Vendor_A', 'Guide', 'intro.md'));
    }

    public function testReturnsNullWhenTheResolverRefusesTheFile(): void
    {
        $tree = $this->treeWith($this->section());

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn($this->sectionRoot);
        $resolver->method('resolveFile')->willReturn(null);

        $repository = $this->repository($tree, $resolver);

        $this->assertNull($repository->getContent('Vendor_A', 'Guide', 'notes.txt'));
    }

    public function testLogsAndReturnsNullWhenTheFileCannotBeRead(): void
    {
        $tree = $this->treeWith($this->section());
        $missing = $this->sectionRoot . '/gone.md';

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn($this->sectionRoot);
        $resolver->method('resolveFile')->willReturn($missing);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('could not read a documentation page'),
                $this->callback(
                    static fn (mixed $context): bool => is_array($context) && ($context['path'] ?? null) === $missing
                )
            );

        $repository = $this->repository($tree, $resolver);

        $this->assertNull($repository->getContent('Vendor_A', 'Guide', 'gone.md'));
    }

    public function testReadsAChangelogThroughTheChangelogResolver(): void
    {
        $tree = $this->treeWith($this->section(true));

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolveChangelogFile')
            ->with('Vendor_A', 'Docs')
            ->willReturn(['path' => $this->changelogRoot . '/CHANGELOG.md', 'fileName' => 'CHANGELOG.md']);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->never())->method('resolveFile');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame(self::CHANGELOG, $repository->getContent('Vendor_A', 'Guide', 'CHANGELOG.md'));
    }

    public function testReturnsNullWhenTheChangelogFileDoesNotResolve(): void
    {
        $tree = $this->treeWith($this->section(true));

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveChangelogFile')->willReturn(null);

        $repository = $this->repository($tree, $resolver);

        $this->assertNull($repository->getContent('Vendor_A', 'Guide', 'CHANGELOG.md'));
    }

    public function testReadsAnAlreadyResolvedSectionWithoutConsultingTheTree(): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->expects($this->never())->method('getSection');

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->with('Vendor_A', 'Docs')->willReturn($this->sectionRoot);
        $resolver->method('resolveFile')->willReturn($this->sectionRoot . '/plain.md');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame(
            self::PLAIN,
            $repository->getContentForSection('Vendor_A', $this->section(), 'plain.md')
        );
    }

    public function testReadsAChangelogForAnAlreadyResolvedSection(): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->expects($this->never())->method('getSection');

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolveChangelogFile')
            ->with('Vendor_A', 'Docs')
            ->willReturn(['path' => $this->changelogRoot . '/CHANGELOG.md', 'fileName' => 'CHANGELOG.md']);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->never())->method('resolveFile');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame(
            self::CHANGELOG,
            $repository->getContentForSection('Vendor_A', $this->section(true), 'CHANGELOG.md')
        );
    }

    public function testReturnsTheFrontMatterOfAChangelog(): void
    {
        $tree = $this->treeWith($this->section(true));

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolveChangelogFile')
            ->with('Vendor_A', 'Docs')
            ->willReturn(['path' => $this->changelogRoot . '/CHANGELOG.md', 'fileName' => 'CHANGELOG.md']);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->never())->method('resolveFile');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame(
            ['title' => 'Release Notes'],
            $repository->getFrontMatter('Vendor_A', 'Guide', 'CHANGELOG.md')
        );
    }

    public function testReturnsTheFrontMatterOfAPageThatHasIt(): void
    {
        $tree = $this->treeWith($this->section());

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn($this->sectionRoot);
        $resolver->method('resolveFile')->willReturn($this->sectionRoot . '/intro.md');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame(['title' => 'Intro Page'], $repository->getFrontMatter('Vendor_A', 'Guide', 'intro.md'));
    }

    public function testReturnsEmptyFrontMatterForAPageWithoutAny(): void
    {
        $tree = $this->treeWith($this->section());

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->method('resolveSectionRoot')->willReturn($this->sectionRoot);
        $resolver->method('resolveFile')->willReturn($this->sectionRoot . '/plain.md');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame([], $repository->getFrontMatter('Vendor_A', 'Guide', 'plain.md'));
    }

    public function testReturnsEmptyFrontMatterWhenTheSectionIsNotInTheAclFilteredTree(): void
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('getSection')->willReturn(null);

        $resolver = $this->createMock(PathResolverInterface::class);
        $resolver->expects($this->never())->method('resolveSectionRoot');
        $resolver->expects($this->never())->method('resolveFile');

        $repository = $this->repository($tree, $resolver);

        $this->assertSame([], $repository->getFrontMatter('Vendor_A', 'Guide', 'intro.md'));
    }

    /**
     * A tree that hands out one section for module "Vendor_A" and section "Guide".
     *
     * @param Section $section
     * @return DocumentationTreeInterface&MockObject
     */
    private function treeWith(Section $section): DocumentationTreeInterface
    {
        $tree = $this->createMock(DocumentationTreeInterface::class);
        $tree->method('getSection')->with('Vendor_A', 'Guide')->willReturn($section);

        return $tree;
    }

    /**
     * @param bool $isChangelog
     * @return Section
     */
    private function section(bool $isChangelog = false): Section
    {
        return new Section('Guide', 'Docs', null, 10, new Category('', 100, [], []), $isChangelog);
    }

    /**
     * @param DocumentationTreeInterface $tree
     * @param PathResolverInterface $resolver
     * @return PageRepository
     */
    private function repository(
        DocumentationTreeInterface $tree,
        PathResolverInterface $resolver
    ): PageRepository {
        return new PageRepository(
            $tree,
            $resolver,
            new File(),
            new FrontMatterReader(new File(), new Parser(), $this->logger),
            $this->logger
        );
    }
}
