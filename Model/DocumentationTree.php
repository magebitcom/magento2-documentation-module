<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\PageInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Model\Tree\AclFilter;
use Magebit\Documentation\Model\Tree\Builder;
use Magebit\Documentation\Model\Tree\Storage;

/**
 * Serves the cached tree, filtered per admin. The cache holds one unfiltered copy for every role.
 */
class DocumentationTree implements DocumentationTreeInterface
{
    /**
     * @var array<string, ModuleDocsInterface>|null
     */
    private ?array $filtered = null;

    /**
     * @param Builder $builder
     * @param Storage $storage
     * @param AclFilter $aclFilter
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly Storage $storage,
        private readonly AclFilter $aclFilter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        if ($this->filtered !== null) {
            return $this->filtered;
        }

        $tree = $this->storage->load();

        if ($tree === null) {
            $tree = $this->builder->build();
            $this->storage->save($tree);
        }

        return $this->filtered = $this->aclFilter->filter($tree);
    }

    /**
     * @inheritDoc
     */
    public function getSection(string $moduleName, string $sectionName): ?SectionInterface
    {
        $module = $this->get()[$moduleName] ?? null;

        if ($module === null) {
            return null;
        }

        foreach ($module->getSections() as $section) {
            if ($section->getName() === $sectionName) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getFirst(): ?array
    {
        foreach ($this->get() as $module) {
            foreach ($module->getSections() as $section) {
                $page = $this->firstPage($section->getRoot());

                if ($page !== null) {
                    return ['module' => $module, 'section' => $section, 'page' => $page];
                }
            }
        }

        return null;
    }

    /**
     * The first page of a category, looking inside its sub-categories when it holds none itself.
     *
     * @param CategoryInterface $category
     * @return PageInterface|null
     */
    private function firstPage(CategoryInterface $category): ?PageInterface
    {
        foreach ($category->getPages() as $page) {
            return $page;
        }

        foreach ($category->getCategories() as $child) {
            $page = $this->firstPage($child);

            if ($page !== null) {
                return $page;
            }
        }

        return null;
    }
}
