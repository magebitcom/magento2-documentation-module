<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\ViewModel;

use Magebit\Documentation\Model\Config\ModuleConfig;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Gives the templates the admin configuration, which a layout argument only accepts as a view model.
 */
class Settings implements ArgumentInterface
{
    /**
     * @param ModuleConfig $config
     */
    public function __construct(private readonly ModuleConfig $config)
    {
    }

    /**
     * Light theme stylesheet to load, without the extension.
     *
     * @return string
     */
    public function getHighlightTheme(): string
    {
        return $this->config->getHighlightTheme();
    }

    /**
     * Whether the documentation search box is shown.
     *
     * @return bool
     */
    public function isSearchEnabled(): bool
    {
        return $this->config->isSearchEnabled();
    }

    /**
     * Language bundles to load on top of the ones highlight.js already knows.
     *
     * @return list<string>
     */
    public function getExtraLanguages(): array
    {
        return $this->config->getExtraLanguages();
    }
}
