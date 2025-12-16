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

class Converter implements ConverterInterface
{
    /**
     * Convert XML DOM to array grouped by module
     *
     * @param DOMDocument $source
     * @return array<string, array<string, mixed>>
     */
    public function convert($source): array
    {
        $result = [];
        $moduleNodes = $source->getElementsByTagName('module');

        foreach ($moduleNodes as $moduleNode) {
            /** @var DOMElement $moduleNode */
            $moduleName = $moduleNode->getAttribute('name');

            if (!$moduleName) {
                continue;
            }

            if (!isset($result[$moduleName])) {
                $result[$moduleName] = [
                    '_title' => null,
                    '_sortOrder' => 100,
                    '_icon' => null,
                ];
            }

            $moduleTitle = $moduleNode->getAttribute('title');
            if ($moduleTitle) {
                $result[$moduleName]['_title'] = $moduleTitle;
            }

            $moduleSortOrder = $moduleNode->getAttribute('sortOrder');
            if ($moduleSortOrder !== '') {
                $result[$moduleName]['_sortOrder'] = (int) $moduleSortOrder;
            }

            $moduleIcon = $moduleNode->getAttribute('icon');
            if ($moduleIcon) {
                $result[$moduleName]['_icon'] = $moduleIcon;
            }

            $documentationNodes = $moduleNode->getElementsByTagName('documentation');

            foreach ($documentationNodes as $docNode) {
                /** @var DOMElement $docNode */
                $name = $docNode->getAttribute('name');
                $path = $docNode->getAttribute('path');
                $acl = $docNode->getAttribute('acl');
                $sortOrder = $docNode->getAttribute('sortOrder');

                if ($name && $path) {
                    $result[$moduleName][$name] = [
                        'name' => $name,
                        'path' => $path,
                        'acl' => $acl ?: null,
                        'sortOrder' => $sortOrder !== '' ? (int) $sortOrder : 100,
                        'type' => 'documentation',
                    ];
                }
            }

            $changelogNodes = $moduleNode->getElementsByTagName('changelog');

            // Only process the last changelog element if multiple are defined
            foreach ($changelogNodes as $changelogNode) {
                /** @var DOMElement $changelogNode */
                $path = $changelogNode->getAttribute('path');
                $acl = $changelogNode->getAttribute('acl');

                if ($path) {
                    // This will overwrite previous changelogs, keeping only the last one
                    $result[$moduleName]['_changelog'] = [
                        'path' => $path,
                        'acl' => $acl ?: null,
                        'type' => 'changelog',
                    ];
                }
            }
        }

        return $result;
    }
}
