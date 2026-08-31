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

    private const CHANGELOG_NAME = 'Changelog';

    /**
     * Convert documentation.xml into a flat module map.
     *
     * @param DOMDocument $source
     * @return array<string, DocModule>
     */
    public function convert($source): array
    {
        $result = [];
        $root = $source->documentElement;

        if ($root === null) {
            return $result;
        }

        foreach ($root->childNodes as $moduleNode) {
            if (!$moduleNode instanceof DOMElement || $moduleNode->nodeName !== 'module') {
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
     * Let a later declaration replace the title, sort order and icon whenever it sets them.
     *
     * Magento's config merge normally collapses duplicate module nodes first; this safety net keeps its rule.
     *
     * @param DocModule $module
     * @param DOMElement $node
     * @return DocModule
     */
    private function applyMetadata(array $module, DOMElement $node): array
    {
        $title = $this->optionalAttribute($node, 'title');
        if ($title !== null) {
            $module['title'] = $title;
        }

        $sortOrder = $this->optionalAttribute($node, 'sortOrder');
        if ($sortOrder !== null) {
            $module['sortOrder'] = (int) $sortOrder;
        }

        $icon = $this->optionalAttribute($node, 'icon');
        if ($icon !== null) {
            $module['icon'] = $icon;
        }

        return $module;
    }

    /**
     * Read the documentation and changelog entries of one module node, keeping their document order.
     *
     * @param DOMElement $moduleNode
     * @return list<DocSection>
     */
    private function readSections(DOMElement $moduleNode): array
    {
        $sections = [];

        foreach ($moduleNode->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $section = match ($node->nodeName) {
                'documentation' => $this->readDocumentation($node),
                'changelog' => $this->readChangelog($node),
                default => null,
            };

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * Build a section from one documentation node, or nothing when it lacks a name or a path.
     *
     * @param DOMElement $node
     * @return DocSection|null
     */
    private function readDocumentation(DOMElement $node): ?array
    {
        $name = $node->getAttribute('name');
        $path = $node->getAttribute('path');

        if ($name === '' || $path === '') {
            return null;
        }

        return [
            'name' => $name,
            'path' => $path,
            'acl' => $this->optionalAttribute($node, 'acl'),
            'sortOrder' => (int) ($this->optionalAttribute($node, 'sortOrder') ?? self::DEFAULT_SORT_ORDER),
            'isChangelog' => false,
        ];
    }

    /**
     * Build a section from one changelog node, or nothing when it lacks a path.
     *
     * @param DOMElement $node
     * @return DocSection|null
     */
    private function readChangelog(DOMElement $node): ?array
    {
        $path = $node->getAttribute('path');

        if ($path === '') {
            return null;
        }

        return [
            'name' => $this->optionalAttribute($node, 'name') ?? self::CHANGELOG_NAME,
            'path' => $path,
            'acl' => $this->optionalAttribute($node, 'acl'),
            'sortOrder' => (int) ($this->optionalAttribute($node, 'sortOrder') ?? self::CHANGELOG_SORT_ORDER),
            'isChangelog' => true,
        ];
    }

    /**
     * Read an attribute, treating only a missing or empty value as absent.
     *
     * @param DOMElement $node
     * @param string $name
     * @return string|null
     */
    private function optionalAttribute(DOMElement $node, string $name): ?string
    {
        $value = $node->getAttribute($name);

        return $value !== '' ? $value : null;
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
