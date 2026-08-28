<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Tree;

use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\DirectoryScannerInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Model\Config\Converter;
use Magebit\Documentation\Model\Config\Data as ConfigData;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;

/**
 * Builds the full documentation tree from configuration and disk, without any ACL filtering.
 *
 * @phpstan-import-type DocModule from Converter
 * @phpstan-import-type DocSection from Converter
 */
class Builder
{
    private const DEFAULT_SORT_ORDER = 100;

    /**
     * @param ConfigData $config
     * @param PathResolverInterface $pathResolver
     * @param DirectoryScannerInterface $scanner
     */
    public function __construct(
        private readonly ConfigData $config,
        private readonly PathResolverInterface $pathResolver,
        private readonly DirectoryScannerInterface $scanner
    ) {
    }

    /**
     * Build every configured module, dropping whatever does not resolve or holds no pages.
     *
     * @return array<string, ModuleDocsInterface> Keyed by module name, ordered by sort order
     */
    public function build(): array
    {
        /** @var array<string, DocModule> $modules */
        $modules = $this->config->get();
        $tree = [];

        foreach ($modules as $moduleName => $module) {
            $sections = $this->buildSections($moduleName, $module['sections']);

            if ($sections === []) {
                continue;
            }

            $tree[$moduleName] = new ModuleDocs(
                $moduleName,
                $module['title'],
                $module['icon'],
                $module['sortOrder'],
                $sections
            );
        }

        return $this->sortModules($tree);
    }

    /**
     * Build the sections of one module, in sort order.
     *
     * @param string $moduleName
     * @param list<DocSection> $sections
     * @return list<SectionInterface>
     */
    private function buildSections(string $moduleName, array $sections): array
    {
        $built = [];

        foreach ($sections as $section) {
            $root = $section['isChangelog']
                ? $this->buildChangelogRoot($moduleName, $section)
                : $this->buildScannedRoot($moduleName, $section);

            if ($root === null) {
                continue;
            }

            $built[] = new Section(
                $section['name'],
                $section['path'],
                $section['acl'],
                $section['sortOrder'],
                $root,
                $section['isChangelog']
            );
        }

        return $this->sortSections($built);
    }

    /**
     * Scan a documentation directory, or nothing when it does not resolve or holds no pages.
     *
     * @param string $moduleName
     * @param DocSection $section
     * @return CategoryInterface|null
     */
    private function buildScannedRoot(string $moduleName, array $section): ?CategoryInterface
    {
        $absoluteRoot = $this->pathResolver->resolveSectionRoot($moduleName, $section['path']);

        if ($absoluteRoot === null) {
            return null;
        }

        $root = $this->scanner->scan($absoluteRoot);

        return $root->isEmpty() ? null : $root;
    }

    /**
     * Wrap a changelog file in a one-page category, or nothing when the file does not resolve.
     *
     * @param string $moduleName
     * @param DocSection $section
     * @return CategoryInterface|null
     */
    private function buildChangelogRoot(string $moduleName, array $section): ?CategoryInterface
    {
        if ($this->pathResolver->resolveChangelogFile($moduleName, $section['path']) === null) {
            return null;
        }

        // The configured path is the page identifier, so it can be resolved again the same way.
        $page = new Page($section['path'], $section['path'], $section['name'], self::DEFAULT_SORT_ORDER, true);

        return new Category('', self::DEFAULT_SORT_ORDER, [$page], []);
    }

    /**
     * Order modules by sort order, then by title.
     *
     * @param array<string,ModuleDocsInterface> $modules
     * @return array<string, ModuleDocsInterface>
     */
    private function sortModules(array $modules): array
    {
        uasort($modules, static function (ModuleDocsInterface $a, ModuleDocsInterface $b): int {
            return $a->getSortOrder() <=> $b->getSortOrder()
                ?: strcasecmp($a->getTitle(), $b->getTitle());
        });

        return $modules;
    }

    /**
     * Order sections by sort order, then by name.
     *
     * @param list<SectionInterface> $sections
     * @return list<SectionInterface>
     */
    private function sortSections(array $sections): array
    {
        usort($sections, static function (SectionInterface $a, SectionInterface $b): int {
            return $a->getSortOrder() <=> $b->getSortOrder()
                ?: strcasecmp($a->getName(), $b->getName());
        });

        return $sections;
    }
}
