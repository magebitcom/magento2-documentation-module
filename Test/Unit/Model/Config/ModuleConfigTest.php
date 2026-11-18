<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Config;

use Magebit\Documentation\Model\Config\ModuleConfig;
use Magebit\Documentation\Model\Config\Source\HighlightLanguage;
use Magebit\Documentation\Model\Config\Source\HighlightTheme;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ModuleConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private ScopeConfigInterface&MockObject $scopeConfig;

    /**
     * @var HighlightTheme&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private HighlightTheme&MockObject $themes;

    /**
     * @var HighlightLanguage&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private HighlightLanguage&MockObject $languages;

    /**
     * @var ModuleConfig
     */
    private ModuleConfig $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->themes = $this->createMock(HighlightTheme::class);
        $this->themes->method('getSelectableNames')->willReturn(['default', 'github']);

        $this->languages = $this->createMock(HighlightLanguage::class);
        $this->languages->method('getSelectableNames')->willReturn(['dockerfile', 'nginx', 'twig']);

        $this->config = new ModuleConfig($this->scopeConfig, $this->themes, $this->languages);
    }

    public function testHighlightThemeComesFromTheAppearanceGroup(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('magebit_documentation/appearance/highlight_theme')
            ->willReturn('default');

        $this->assertSame('default', $this->config->getHighlightTheme());
    }

    public function testHighlightThemeFallsBackToGithubNeverSaved(): void
    {
        $this->assertHighlightThemeFallsBackToGithub(null);
    }

    public function testHighlightThemeFallsBackToGithubEmptyString(): void
    {
        $this->assertHighlightThemeFallsBackToGithub('');
    }

    public function testHighlightThemeFallsBackToGithubOnlySpaces(): void
    {
        $this->assertHighlightThemeFallsBackToGithub('   ');
    }

    public function testHighlightThemeFallsBackToGithubThemeNoLongerShips(): void
    {
        $this->assertHighlightThemeFallsBackToGithub('monokai');
    }

    public function testHighlightThemeFallsBackToGithubDarkVariantIsNotALightTheme(): void
    {
        $this->assertHighlightThemeFallsBackToGithub('github-dark');
    }

    /**
     * @param mixed $stored
     */
    private function assertHighlightThemeFallsBackToGithub(mixed $stored): void
    {
        $this->scopeConfig->method('getValue')->willReturn($stored);

        $this->assertSame('github', $this->config->getHighlightTheme());
    }

    public function testSearchEnabledFollowsTheStoredFlagEnabled(): void
    {
        $this->assertSearchEnabledFollowsTheStoredFlag(true);
    }

    public function testSearchEnabledFollowsTheStoredFlagDisabled(): void
    {
        $this->assertSearchEnabledFollowsTheStoredFlag(false);
    }

    /**
     * @param bool $stored
     */
    private function assertSearchEnabledFollowsTheStoredFlag(bool $stored): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('magebit_documentation/search/enabled')
            ->willReturn($stored);

        $this->assertSame($stored, $this->config->isSearchEnabled());
    }

    public function testExtraLanguagesSplitsTheStoredList(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('magebit_documentation/appearance/extra_languages')
            ->willReturn('nginx,twig');

        $this->assertSame(['nginx', 'twig'], $this->config->getExtraLanguages());
    }

    public function testExtraLanguagesDropsBlanksAndRepeats(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(' nginx , ,twig ,nginx');

        $this->assertSame(['nginx', 'twig'], $this->config->getExtraLanguages());
    }

    public function testExtraLanguagesDropsOnesThatNoLongerShip(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('nginx,perl,twig');

        $this->assertSame(['nginx', 'twig'], $this->config->getExtraLanguages());
    }

    public function testExtraLanguagesIsEmptyWhenNothingShipsAnyMore(): void
    {
        $languages = $this->createMock(HighlightLanguage::class);
        $languages->method('getSelectableNames')->willReturn([]);
        $config = new ModuleConfig($this->scopeConfig, $this->themes, $languages);

        $this->scopeConfig->method('getValue')->willReturn('nginx,twig');

        $this->assertSame([], $config->getExtraLanguages());
    }

    public function testExtraLanguagesIsEmptyWhenNothingIsSelectedNeverSaved(): void
    {
        $this->assertExtraLanguagesIsEmptyWhenNothingIsSelected(null);
    }

    public function testExtraLanguagesIsEmptyWhenNothingIsSelectedEmptyString(): void
    {
        $this->assertExtraLanguagesIsEmptyWhenNothingIsSelected('');
    }

    public function testExtraLanguagesIsEmptyWhenNothingIsSelectedOnlySeparators(): void
    {
        $this->assertExtraLanguagesIsEmptyWhenNothingIsSelected(' , , ');
    }

    /**
     * @param mixed $stored
     */
    private function assertExtraLanguagesIsEmptyWhenNothingIsSelected(mixed $stored): void
    {
        $this->scopeConfig->method('getValue')->willReturn($stored);

        $this->assertSame([], $this->config->getExtraLanguages());
    }
}
