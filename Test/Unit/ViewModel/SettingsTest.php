<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\ViewModel;

use Magebit\Documentation\Model\Config\ModuleConfig;
use Magebit\Documentation\ViewModel\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testGivesTheTemplateTheConfiguredTheme(): void
    {
        $settings = new Settings($this->config('nord', true, ['twig']));

        $this->assertSame('nord', $settings->getHighlightTheme());
    }

    public function testGivesTheTemplateTheSearchSwitchAndTheExtraLanguages(): void
    {
        $settings = new Settings($this->config('github', false, ['nginx', 'dockerfile']));

        $this->assertFalse($settings->isSearchEnabled());
        $this->assertSame(['nginx', 'dockerfile'], $settings->getExtraLanguages());
    }

    /**
     * A configuration reader answering exactly what the test asked for.
     *
     * @param string $theme
     * @param bool $searchEnabled
     * @param list<string> $languages
     * @return ModuleConfig
     */
    private function config(string $theme, bool $searchEnabled, array $languages): ModuleConfig
    {
        $config = $this->createMock(ModuleConfig::class);
        $config->method('getHighlightTheme')->willReturn($theme);
        $config->method('isSearchEnabled')->willReturn($searchEnabled);
        $config->method('getExtraLanguages')->willReturn($languages);

        return $config;
    }
}
