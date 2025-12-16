<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Data;

use Magebit\Documentation\Api\Data\SearchResultInterface;

/**
 * Search result data object
 */
class SearchResult implements SearchResultInterface
{
    /**
     * @param string $module
     * @param string $moduleTitle
     * @param string $feature
     * @param string $file
     * @param string $displayName
     * @param string $url
     * @param string $breadcrumb
     */
    public function __construct(
        private readonly string $module,
        private readonly string $moduleTitle,
        private readonly string $feature,
        private readonly string $file,
        private readonly string $displayName,
        private readonly string $url,
        private readonly string $breadcrumb
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getModule(): string
    {
        return $this->module;
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
    public function getFeature(): string
    {
        return $this->feature;
    }

    /**
     * @inheritDoc
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @inheritDoc
     */
    public function getUrl(): string
    {
        return $this->url;
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
    public function toArray(): array
    {
        return [
            'module' => $this->module,
            'moduleTitle' => $this->moduleTitle,
            'feature' => $this->feature,
            'file' => $this->file,
            'displayName' => $this->displayName,
            'url' => $this->url,
            'breadcrumb' => $this->breadcrumb,
        ];
    }
}
