<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Config;

use DOMDocument;
use DOMElement;
use Magento\Framework\Config\ConverterInterface;

/**
 * Turns documentation.xml into plain module and section arrays.
 *
 * @phpstan-type DocSection array{name: string, path: string, acl: string|null, sortOrder: int, isChangelog: bool}
 * @phpstan-type DocModule array{title: string, sortOrder: int, icon: string|null, sections: list<DocSection>}
 */
class Converter implements ConverterInterface
{
    private const DEFAULT_SORT_ORDER = 100;

    private const CHANGELOG_SORT_ORDER = 1000;

    /**
     * Convert documentation.xml into a flat module map.
     *
     * @param DOMDocument $source
     * @return array<string, DocModule>
     */
    public function convert($source): array
    {
        $result = [];

        foreach ($source->getElementsByTagName('module') as $moduleNode) {
            if (!$moduleNode instanceof DOMElement) {
                continue;
            }

            $moduleName = $moduleNode->getAttribute('name');
            if ($moduleName === '') {
                continue;
            }

            $result[$moduleName] ??= [
                'title' => '',
                'sortOrder' => self::DEFAULT_SORT_ORDER,
                'icon' => null,
                'sections' => [],
            ];

            $result[$moduleName] = $this->applyMetadata($result[$moduleName], $moduleNode);
            $result[$moduleName]['sections'] = $this->mergeSections(
                $result[$moduleName]['sections'],
                $this->readSections($moduleNode)
            );
        }

        return $result;
    }

    /**
     * Take the first title, but let a later declaration replace the sort order and icon.
     *
     * @param DocModule $module
     * @param DOMElement $node
     * @return DocModule
     */
    private function applyMetadata(array $module, DOMElement $node): array
    {
        if ($module['title'] === '' && $node->getAttribute('title') !== '') {
            $module['title'] = $node->getAttribute('title');
        }

        if ($node->getAttribute('sortOrder') !== '') {
            $module['sortOrder'] = (int) $node->getAttribute('sortOrder');
        }

        if ($node->getAttribute('icon') !== '') {
            $module['icon'] = $node->getAttribute('icon');
        }

        return $module;
    }

    /**
     * Read every documentation and changelog entry declared by one module node.
     *
     * @param DOMElement $moduleNode
     * @return list<DocSection>
     */
    private function readSections(DOMElement $moduleNode): array
    {
        $sections = [];

        foreach ($moduleNode->getElementsByTagName('documentation') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $name = $node->getAttribute('name');
            $path = $node->getAttribute('path');

            if ($name === '' || $path === '') {
                continue;
            }

            $sections[] = [
                'name' => $name,
                'path' => $path,
                'acl' => $node->getAttribute('acl') ?: null,
                'sortOrder' => $node->getAttribute('sortOrder') !== ''
                    ? (int) $node->getAttribute('sortOrder')
                    : self::DEFAULT_SORT_ORDER,
                'isChangelog' => false,
            ];
        }

        foreach ($moduleNode->getElementsByTagName('changelog') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $path = $node->getAttribute('path');
            if ($path === '') {
                continue;
            }

            $sections[] = [
                'name' => $node->getAttribute('name') ?: 'Changelog',
                'path' => $path,
                'acl' => $node->getAttribute('acl') ?: null,
                'sortOrder' => $node->getAttribute('sortOrder') !== ''
                    ? (int) $node->getAttribute('sortOrder')
                    : self::CHANGELOG_SORT_ORDER,
                'isChangelog' => true,
            ];
        }

        return $sections;
    }

    /**
     * Append sections, letting a later declaration replace an earlier one with the same name.
     *
     * @param list<DocSection> $existing
     * @param list<DocSection> $incoming
     * @return list<DocSection>
     */
    private function mergeSections(array $existing, array $incoming): array
    {
        $byName = [];

        foreach ([...$existing, ...$incoming] as $section) {
            $byName[$section['name']] = $section;
        }

        return array_values($byName);
    }
}
