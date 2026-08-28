<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Reads the admin configuration of the documentation viewer.
 */
class ModuleConfig
{
    private const XML_PATH_HIGHLIGHT_THEME = 'magebit_documentation/appearance/highlight_theme';

    private const XML_PATH_EXTRA_LANGUAGES = 'magebit_documentation/appearance/extra_languages';

    private const XML_PATH_SEARCH_ENABLED = 'magebit_documentation/search/enabled';

    private const DEFAULT_HIGHLIGHT_THEME = 'github';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Name of the shipped theme stylesheet, without the extension.
     *
     * @return string
     */
    public function getHighlightTheme(): string
    {
        $theme = $this->getTrimmedValue(self::XML_PATH_HIGHLIGHT_THEME);

        return $theme === '' ? self::DEFAULT_HIGHLIGHT_THEME : $theme;
    }

    /**
     * Whether the documentation search box is shown.
     *
     * @return bool
     */
    public function isSearchEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SEARCH_ENABLED);
    }

    /**
     * Language bundles to load on top of the ones the core highlight.js file already knows.
     *
     * @return list<string>
     */
    public function getExtraLanguages(): array
    {
        $stored = $this->getTrimmedValue(self::XML_PATH_EXTRA_LANGUAGES);

        if ($stored === '') {
            return [];
        }

        $languages = [];

        foreach (explode(',', $stored) as $language) {
            $language = trim($language);

            if ($language !== '' && !in_array($language, $languages, true)) {
                $languages[] = $language;
            }
        }

        return $languages;
    }

    /**
     * Read one configuration value as a trimmed string.
     *
     * @param string $path
     * @return string
     */
    private function getTrimmedValue(string $path): string
    {
        $value = $this->scopeConfig->getValue($path);

        return is_scalar($value) ? trim((string)$value) : '';
    }
}
