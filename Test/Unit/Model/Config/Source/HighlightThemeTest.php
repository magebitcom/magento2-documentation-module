<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Config\Source;

use Magebit\Documentation\Model\Config\Source\HighlightTheme;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HighlightThemeTest extends TestCase
{
    /**
     * @var string
     */
    private string $root;

    /**
     * @var string
     */
    private string $themeDirectory;

    /**
     * @var File
     */
    private File $fileDriver;

    /**
     * @var ComponentRegistrarInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private ComponentRegistrarInterface&MockObject $registrar;

    protected function setUp(): void
    {
        $this->root = (string)realpath(sys_get_temp_dir()) . '/magedoc-theme-' . uniqid('', true);
        $this->themeDirectory = $this->root . '/view/adminhtml/web/css/highlight';
        $this->fileDriver = new File();

        $this->fileDriver->createDirectory($this->themeDirectory);

        $this->registrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->registrar->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'Magebit_Documentation')
            ->willReturn($this->root);
    }

    protected function tearDown(): void
    {
        if ($this->fileDriver->isExists($this->root)) {
            $this->fileDriver->deleteDirectory($this->root);
        }
    }

    public function testListsEveryShippedLightThemeInAlphabeticalOrder(): void
    {
        $this->shipThemes(['github.css', 'default.css', 'nord.css']);

        $this->assertSame(
            [
                ['value' => 'default', 'label' => 'Default'],
                ['value' => 'github', 'label' => 'Github'],
                ['value' => 'nord', 'label' => 'Nord'],
            ],
            $this->createSource()->toOptionArray()
        );
    }

    public function testDoesNotOfferDarkVariantsAsAChoice(): void
    {
        $this->shipThemes(['github.css', 'github-dark.css']);

        $this->assertSame(
            [['value' => 'github', 'label' => 'Github']],
            $this->createSource()->toOptionArray()
        );
    }

    public function testIgnoresFilesThatAreNotStylesheets(): void
    {
        $this->shipThemes(['github.css', 'README.md', 'notes.txt']);

        $this->assertSame(
            [['value' => 'github', 'label' => 'Github']],
            $this->createSource()->toOptionArray()
        );
    }

    public function testIgnoresDirectoriesNamedLikeAStylesheet(): void
    {
        $this->shipThemes(['github.css']);
        $this->fileDriver->createDirectory($this->themeDirectory . '/leftover.css');

        $this->assertSame(
            [['value' => 'github', 'label' => 'Github']],
            $this->createSource()->toOptionArray()
        );
    }

    public function testReturnsNoOptionsWhenTheDirectoryIsMissing(): void
    {
        $this->fileDriver->deleteDirectory($this->root . '/view');

        $this->assertSame([], $this->createSource()->toOptionArray());
    }

    public function testReturnsNoOptionsWhenTheModuleIsNotRegistered(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturn(null);

        $this->assertSame([], (new HighlightTheme($registrar, $this->fileDriver))->toOptionArray());
    }

    /**
     * @param list<string> $fileNames
     * @return void
     */
    private function shipThemes(array $fileNames): void
    {
        foreach ($fileNames as $fileName) {
            $this->fileDriver->filePutContents($this->themeDirectory . '/' . $fileName, '.hljs{}');
        }
    }

    /**
     * @return HighlightTheme
     */
    private function createSource(): HighlightTheme
    {
        return new HighlightTheme($this->registrar, $this->fileDriver);
    }
}
