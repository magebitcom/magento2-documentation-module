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
     * @var ComponentRegistrarInterface&MockObject
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting
    private ComponentRegistrarInterface&MockObject $registrar;

    protected function setUp(): void
    {
        $this->root = (string)realpath(sys_get_temp_dir()) . '/magedoc-theme-' . uniqid('', true);

        mkdir($this->root . '/view/adminhtml/web/css/highlight', 0777, true);

        $this->registrar = $this->createMock(ComponentRegistrarInterface::class);
        $this->registrar->method('getPath')
            ->with(ComponentRegistrar::MODULE, 'Magebit_Documentation')
            ->willReturn($this->root);
    }

    protected function tearDown(): void
    {
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testListsEveryShippedThemeInAlphabeticalOrder(): void
    {
        $this->shipThemes(['github.css', 'github-dark.css', 'default.css']);

        $this->assertSame(
            [
                ['value' => 'default', 'label' => 'Default'],
                ['value' => 'github', 'label' => 'Github'],
                ['value' => 'github-dark', 'label' => 'Github Dark'],
            ],
            $this->createSource()->toOptionArray()
        );
    }

    public function testIgnoresFilesThatAreNotStylesheets(): void
    {
        $this->shipThemes(['github.css', 'README.md', 'notes.txt']);
        mkdir($this->root . '/view/adminhtml/web/css/highlight/extra');

        $this->assertSame(
            [['value' => 'github', 'label' => 'Github']],
            $this->createSource()->toOptionArray()
        );
    }

    public function testReturnsNoOptionsWhenTheDirectoryIsMissing(): void
    {
        // phpcs:ignore Magento2.Security.InsecureFunction
        exec('rm -rf ' . escapeshellarg($this->root . '/view'));

        $this->assertSame([], $this->createSource()->toOptionArray());
    }

    public function testReturnsNoOptionsWhenTheModuleIsNotRegistered(): void
    {
        $registrar = $this->createMock(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturn(null);

        $this->assertSame([], (new HighlightTheme($registrar, new File()))->toOptionArray());
    }

    /**
     * @param list<string> $fileNames
     * @return void
     */
    private function shipThemes(array $fileNames): void
    {
        foreach ($fileNames as $fileName) {
            file_put_contents($this->root . '/view/adminhtml/web/css/highlight/' . $fileName, '.hljs{}');
        }
    }

    /**
     * @return HighlightTheme
     */
    private function createSource(): HighlightTheme
    {
        return new HighlightTheme($this->registrar, new File());
    }
}
