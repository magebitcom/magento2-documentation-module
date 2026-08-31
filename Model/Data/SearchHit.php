<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Data;

use Magebit\Documentation\Api\Data\SearchHitInterface;

/**
 * One page the search matched, with its score and snippet.
 */
class SearchHit implements SearchHitInterface
{
    /**
     * @param string $moduleName
     * @param string $moduleTitle
     * @param string $sectionName
     * @param string $relativePath
     * @param string $title
     * @param string $snippet
     * @param string $breadcrumb
     * @param int $score
     */
    public function __construct(
        private readonly string $moduleName,
        private readonly string $moduleTitle,
        private readonly string $sectionName,
        private readonly string $relativePath,
        private readonly string $title,
        private readonly string $snippet,
        private readonly string $breadcrumb,
        private readonly int $score
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    /**
     * @inheritDoc
     */
    public function getModuleTitle(): string
    {
        return $this->moduleTitle;
    }

    /**
     * @inheritDoc
     */
    public function getSectionName(): string
    {
        return $this->sectionName;
    }

    /**
     * @inheritDoc
     */
    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @inheritDoc
     */
    public function getSnippet(): string
    {
        return $this->snippet;
    }

    /**
     * @inheritDoc
     */
    public function getBreadcrumb(): string
    {
        return $this->breadcrumb;
    }

    /**
     * @inheritDoc
     */
    public function getScore(): int
    {
        return $this->score;
    }
}
