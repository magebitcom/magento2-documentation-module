<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Console\Command;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Model\Tree\Builder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows every registered documentation section and where it resolves to on disk. The tree is
 * taken unfiltered, because the command line has no admin session to filter it against.
 */
class ListDocumentation extends Command
{
    private const NAME = 'magebit:documentation:list';

    /**
     * @param Builder $builder
     * @param PathResolverInterface $pathResolver
     * @param string|null $name
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly PathResolverInterface $pathResolver,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName(self::NAME)
            ->setDescription('List every registered documentation section and where it resolves to.');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tree = $this->builder->build();

        if ($tree === []) {
            $output->writeln('<comment>No documentation is registered.</comment>');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Module', 'Title', 'Section', 'Resolved path', 'Pages']);

        foreach ($tree as $module) {
            foreach ($module->getSections() as $section) {
                $table->addRow([
                    $module->getModuleName(),
                    $module->getTitle(),
                    $section->getName(),
                    $this->resolvedPath($module->getModuleName(), $section),
                    (string)$this->countPages($section->getRoot()),
                ]);
            }
        }

        $table->render();

        return Command::SUCCESS;
    }

    /**
     * Where a section points on disk, falling back to the configured path when it no longer resolves.
     *
     * @param string $moduleName
     * @param SectionInterface $section
     * @return string
     */
    private function resolvedPath(string $moduleName, SectionInterface $section): string
    {
        if ($section->isChangelog()) {
            $changelog = $this->pathResolver->resolveChangelogFile($moduleName, $section->getPath());

            return $changelog === null ? $section->getPath() : $changelog['path'];
        }

        return $this->pathResolver->resolveSectionRoot($moduleName, $section->getPath()) ?? $section->getPath();
    }

    /**
     * Count the pages of a category and of everything below it.
     *
     * @param CategoryInterface $category
     * @return int
     */
    private function countPages(CategoryInterface $category): int
    {
        $count = count($category->getPages());

        foreach ($category->getCategories() as $child) {
            $count += $this->countPages($child);
        }

        return $count;
    }
}
