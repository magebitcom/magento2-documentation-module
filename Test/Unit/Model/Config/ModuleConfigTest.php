<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Config;

use Magebit\Documentation\Model\Config\ModuleConfig;
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
     * @var ModuleConfig
     */
    private ModuleConfig $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new ModuleConfig($this->scopeConfig);
    }

    public function testHighlightThemeComesFromTheAppearanceGroup(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('magebit_documentation/appearance/highlight_theme')
            ->willReturn('github-dark');

        $this->assertSame('github-dark', $this->config->getHighlightTheme());
    }

    /**
     * @dataProvider blankThemeProvider
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
    public static function blankThemeProvider(): array
    {
        return [
            'never saved' => [null],
            'empty string' => [''],
            'only spaces' => ['   '],
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

        $languages = $this->config->getExtraLanguages();

        $this->assertSame(['nginx', 'twig'], $languages);
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
