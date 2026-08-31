<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Config;

use Magebit\Documentation\Model\Config\ModuleConfig;
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
     * @var ModuleConfig
     */
    private ModuleConfig $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->themes = $this->createMock(HighlightTheme::class);
        $this->themes->method('getSelectableNames')->willReturn(['default', 'github']);

        $this->config = new ModuleConfig($this->scopeConfig, $this->themes);
    }

    public function testHighlightThemeComesFromTheAppearanceGroup(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('magebit_documentation/appearance/highlight_theme')
            ->willReturn('default');

        $this->assertSame('default', $this->config->getHighlightTheme());
    }

    /**
     * @dataProvider unusableThemeProvider
     * @param mixed $stored
     */
    public function testHighlightThemeFallsBackToGithub(mixed $stored): void
    {
        $this->scopeConfig->method('getValue')->willReturn($stored);

        $this->assertSame('github', $this->config->getHighlightTheme());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unusableThemeProvider(): array
    {
        return [
            'never saved' => [null],
            'empty string' => [''],
            'only spaces' => ['   '],
            'theme no longer ships' => ['monokai'],
            'dark variant is not a light theme' => ['github-dark'],
        ];
    }

    /**
     * @dataProvider searchFlagProvider
     * @param bool $stored
     */
    public function testSearchEnabledFollowsTheStoredFlag(bool $stored): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('magebit_documentation/search/enabled')
            ->willReturn($stored);

        $this->assertSame($stored, $this->config->isSearchEnabled());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function searchFlagProvider(): array
    {
        return [
            'enabled' => [true],
            'disabled' => [false],
        ];
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

    /**
     * @dataProvider emptyLanguagesProvider
     * @param mixed $stored
     */
    public function testExtraLanguagesIsEmptyWhenNothingIsSelected(mixed $stored): void
    {
        $this->scopeConfig->method('getValue')->willReturn($stored);

        $this->assertSame([], $this->config->getExtraLanguages());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyLanguagesProvider(): array
    {
        return [
            'never saved' => [null],
            'empty string' => [''],
            'only separators' => [' , , '],
        ];
    }
}
