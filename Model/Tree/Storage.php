<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Tree;

use InvalidArgumentException;
use Magebit\Documentation\Api\Data\CategoryInterface;
use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\PageInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Model\Cache\Type as CacheType;
use Magebit\Documentation\Model\Data\Category;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magebit\Documentation\Model\Data\Page;
use Magebit\Documentation\Model\Data\Section;
use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Caches the unfiltered documentation tree as plain arrays, so no object graph is ever unserialized.
 */
class Storage
{
    private const CACHE_KEY = 'magebit_documentation_tree';

    /**
     * @param CacheType $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly CacheType $cache,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Read the cached tree, or nothing when it is missing or no longer matches the current shape.
     *
     * @return array<string, ModuleDocsInterface>|null
     */
    public function load(): ?array
    {
        $cached = $this->cache->load(self::CACHE_KEY);

        if (!is_string($cached) || $cached === '') {
            return null;
        }

        try {
            $data = $this->serializer->unserialize($cached);
        } catch (InvalidArgumentException $e) {
            return null;
        }

        return $this->readTree($data);
    }

    /**
     * Store the tree, tagged with this cache type and with the config cache it is built from.
     *
     * @param array<string,ModuleDocsInterface> $tree
     * @return void
     */
    public function save(array $tree): void
    {
        $data = [];

        foreach ($tree as $moduleName => $module) {
            $data[$moduleName] = $this->writeModule($module);
        }

        $serialized = $this->serializer->serialize($data);

        // Nothing worth caching when the tree cannot be serialized; the next request rebuilds it.
        if (is_string($serialized)) {
            $this->cache->save($serialized, self::CACHE_KEY, [ConfigCacheType::CACHE_TAG]);
        }
    }

    /**
     * Flatten one module into plain arrays.
     *
     * @param ModuleDocsInterface $module
     * @return array<string, mixed>
     */
    private function writeModule(ModuleDocsInterface $module): array
    {
        return [
            'moduleName' => $module->getModuleName(),
            'title' => $module->getTitle(),
            'icon' => $module->getIcon(),
            'sortOrder' => $module->getSortOrder(),
            'sections' => array_map(fn (SectionInterface $s): array => $this->writeSection($s), $module->getSections()),
        ];
    }

    /**
     * Flatten one section into plain arrays.
     *
     * @param SectionInterface $section
     * @return array<string, mixed>
     */
    private function writeSection(SectionInterface $section): array
    {
        return [
            'name' => $section->getName(),
            'path' => $section->getPath(),
            'acl' => $section->getAcl(),
            'sortOrder' => $section->getSortOrder(),
            'isChangelog' => $section->isChangelog(),
            'root' => $this->writeCategory($section->getRoot()),
        ];
    }

    /**
     * Flatten one category and everything below it.
     *
     * @param CategoryInterface $category
     * @return array<string, mixed>
     */
    private function writeCategory(CategoryInterface $category): array
    {
        return [
            'label' => $category->getLabel(),
            'sortOrder' => $category->getSortOrder(),
            'pages' => array_map(fn (PageInterface $p): array => $this->writePage($p), $category->getPages()),
            'categories' => array_map(
                fn (CategoryInterface $c): array => $this->writeCategory($c),
                $category->getCategories()
            ),
        ];
    }

    /**
     * Flatten one page.
     *
     * @param PageInterface $page
     * @return array<string, mixed>
     */
    private function writePage(PageInterface $page): array
    {
        return [
            'relativePath' => $page->getRelativePath(),
            'fileName' => $page->getFileName(),
            'title' => $page->getTitle(),
            'sortOrder' => $page->getSortOrder(),
            'isIndex' => $page->isIndex(),
        ];
    }

    /**
     * Rebuild the whole tree, or nothing when any part of it does not match.
     *
     * @param mixed $data
     * @return array<string, ModuleDocsInterface>|null
     */
    private function readTree(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $tree = [];

        foreach ($data as $moduleName => $module) {
            if (!is_string($moduleName)) {
                return null;
            }

            $read = $this->readModule($module);

            if ($read === null) {
                return null;
            }

            $tree[$moduleName] = $read;
        }

        return $tree;
    }

    /**
     * Rebuild one module, or nothing when a field is missing or has the wrong type.
     *
     * @param mixed $data
     * @return ModuleDocsInterface|null
     */
    private function readModule(mixed $data): ?ModuleDocsInterface
    {
        if (!is_array($data)) {
            return null;
        }

        $moduleName = $this->readString($data, 'moduleName');
        $title = $this->readString($data, 'title');
        $sortOrder = $this->readInt($data, 'sortOrder');
        $icon = $data['icon'] ?? null;
        $sections = $this->readSections($data['sections'] ?? null);

        if ($moduleName === null || $title === null || $sortOrder === null || $sections === null) {
            return null;
        }

        if ($icon !== null && !is_string($icon)) {
            return null;
        }

        return new ModuleDocs($moduleName, $title, $icon, $sortOrder, $sections);
    }

    /**
     * Rebuild a list of sections, or nothing when any of them does not match.
     *
     * @param mixed $data
     * @return list<SectionInterface>|null
     */
    private function readSections(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $sections = [];

        foreach ($data as $section) {
            $read = $this->readSection($section);

            if ($read === null) {
                return null;
            }

            $sections[] = $read;
        }

        return $sections;
    }

    /**
     * Rebuild one section, or nothing when a field is missing or has the wrong type.
     *
     * @param mixed $data
     * @return SectionInterface|null
     */
    private function readSection(mixed $data): ?SectionInterface
    {
        if (!is_array($data)) {
            return null;
        }

        $name = $this->readString($data, 'name');
        $path = $this->readString($data, 'path');
        $sortOrder = $this->readInt($data, 'sortOrder');
        $isChangelog = $data['isChangelog'] ?? null;
        $acl = $data['acl'] ?? null;
        $root = $this->readCategory($data['root'] ?? null);

        if ($name === null || $path === null || $sortOrder === null || $root === null || !is_bool($isChangelog)) {
            return null;
        }

        if ($acl !== null && !is_string($acl)) {
            return null;
        }

        return new Section($name, $path, $acl, $sortOrder, $root, $isChangelog);
    }

    /**
     * Rebuild one category and everything below it, or nothing when a field does not match.
     *
     * @param mixed $data
     * @return CategoryInterface|null
     */
    private function readCategory(mixed $data): ?CategoryInterface
    {
        if (!is_array($data)) {
            return null;
        }

        $label = $this->readString($data, 'label');
        $sortOrder = $this->readInt($data, 'sortOrder');

        if ($label === null || $sortOrder === null || !is_array($data['pages'] ?? null)) {
            return null;
        }

        $pages = [];

        foreach ($data['pages'] as $page) {
            $read = $this->readPage($page);

            if ($read === null) {
                return null;
            }

            $pages[] = $read;
        }

        if (!is_array($data['categories'] ?? null)) {
            return null;
        }

        $categories = [];

        foreach ($data['categories'] as $category) {
            $read = $this->readCategory($category);

            if ($read === null) {
                return null;
            }

            $categories[] = $read;
        }

        return new Category($label, $sortOrder, $pages, $categories);
    }

    /**
     * Rebuild one page, or nothing when a field is missing or has the wrong type.
     *
     * @param mixed $data
     * @return PageInterface|null
     */
    private function readPage(mixed $data): ?PageInterface
    {
        if (!is_array($data)) {
            return null;
        }

        $relativePath = $this->readString($data, 'relativePath');
        $fileName = $this->readString($data, 'fileName');
        $title = $this->readString($data, 'title');
        $sortOrder = $this->readInt($data, 'sortOrder');
        $isIndex = $data['isIndex'] ?? null;

        if ($relativePath === null || $fileName === null || $title === null || $sortOrder === null) {
            return null;
        }

        if (!is_bool($isIndex)) {
            return null;
        }

        return new Page($relativePath, $fileName, $title, $sortOrder, $isIndex);
    }

    /**
     * Read a string field, or nothing when it is missing or is not a string.
     *
     * @param array<array-key,mixed> $data
     * @param string $key
     * @return string|null
     */
    private function readString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Read a whole-number field, or nothing when it is missing or is not one.
     *
     * @param array<array-key,mixed> $data
     * @param string $key
     * @return int|null
     */
    private function readInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}
