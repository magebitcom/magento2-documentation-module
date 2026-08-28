<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Config\Source;

use Magebit\Documentation\Model\Config\Source\HighlightLanguage;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HighlightLanguageTest extends TestCase
{
    /**
     * @var string
     */
    private string $root;

    /**
     * @var string
     */
    private string $languageDirectory;

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
        $this->root = (string)realpath(sys_get_temp_dir()) . '/magedoc-language-' . uniqid('', true);
        $this->languageDirectory = $this->root . '/view/adminhtml/web/js/vendor/highlight/languages';
        $this->fileDriver = new File();

        $this->fileDriver->createDirectory($this->languageDirectory);

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

    public function testListsEveryShippedLanguageFileInAlphabeticalOrder(): void
    {
        $this->shipLanguages(['nginx.min.js', 'dockerfile.min.js', 'php-template.min.js']);

        $this->assertSame(
            [
                ['value' => 'dockerfile', 'label' => 'Dockerfile'],
                ['value' => 'nginx', 'label' => 'Nginx'],
                ['value' => 'php-template', 'label' => 'Php Template'],
            ],
            $this->createSource()->toOptionArray()
        );
    }

    public function testIgnoresFilesThatAreNotLanguageBundles(): void
    {
        $this->shipLanguages(['nginx.min.js', 'LICENSE', 'notes.js']);

        $this->assertSame(
            [['value' => 'nginx', 'label' => 'Nginx']],
            $this->createSource()->toOptionArray()
        );
    }

    public function testKeepsDarkSoundingNamesBecauseOnlyThemesPairUp(): void
    {
        $this->shipLanguages(['nginx.min.js', 'x-dark.min.js']);

        $this->assertSame(
            [
                ['value' => 'nginx', 'label' => 'Nginx'],
                ['value' => 'x-dark', 'label' => 'X Dark'],
            ],
            $this->createSource()->toOptionArray()
        );
    }

    public function testReturnsNoOptionsWhenTheDirectoryIsMissing(): void
    {
        $this->fileDriver->deleteDirectory($this->root . '/view');

        $this->assertSame([], $this->createSource()->toOptionArray());
    }

    /**
     * @param list<string> $fileNames
     * @return void
     */
    private function shipLanguages(array $fileNames): void
    {
        foreach ($fileNames as $fileName) {
            $this->fileDriver->filePutContents(
                $this->languageDirectory . '/' . $fileName,
                'hljs.registerLanguage();'
            );
        }
    }

    /**
     * @return HighlightLanguage
     */
    private function createSource(): HighlightLanguage
    {
        return new HighlightLanguage($this->registrar, $this->fileDriver);
    }
}
