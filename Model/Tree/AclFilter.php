<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Tree;

use Magebit\Documentation\Api\Data\ModuleDocsInterface;
use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Model\Data\ModuleDocs;
use Magento\Framework\AuthorizationInterface;

/**
 * Reduces the cached tree to what the current admin is allowed to see.
 */
class AclFilter
{
    /**
     * @param AuthorizationInterface $authorization
     */
    public function __construct(private readonly AuthorizationInterface $authorization)
    {
    }

    /**
     * Drop sections the admin may not see, and modules left without any section.
     *
     * @param array<string,ModuleDocsInterface> $tree
     * @return array<string, ModuleDocsInterface>
     */
    public function filter(array $tree): array
    {
        $filtered = [];

        foreach ($tree as $moduleName => $module) {
            $sections = $this->allowedSections($module->getSections());

            if ($sections === []) {
                continue;
            }

            $filtered[$moduleName] = new ModuleDocs(
                $module->getModuleName(),
                $module->getTitle(),
                $module->getIcon(),
                $module->getSortOrder(),
                $sections
            );
        }

        return $filtered;
    }

    /**
     * Keep the sections without an ACL resource and those the admin is granted.
     *
     * @param list<SectionInterface> $sections
     * @return list<SectionInterface>
     */
    private function allowedSections(array $sections): array
    {
        $allowed = [];

        foreach ($sections as $section) {
            $acl = $section->getAcl();

            if ($acl === null || $this->authorization->isAllowed($acl)) {
                $allowed[] = $section;
            }
        }

        return $allowed;
    }
}
