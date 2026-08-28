<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Scanner;

use Magebit\Documentation\Model\Scanner\FileNameParser;
use PHPUnit\Framework\TestCase;

class FileNameParserTest extends TestCase
{
    /**
     * @var FileNameParser
     */
    private FileNameParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FileNameParser();
    }

    /**
     * @param string $fileName
     * @param array{sortOrder: int, label: string, isIndex: bool} $expected
     * @return void
     * @dataProvider fileNameProvider
     */
    public function testParse(string $fileName, array $expected): void
    {
        $this->assertSame($expected, $this->parser->parse($fileName));
    }

    /**
     * @return array<string, array{0: string, 1: array{sortOrder: int, label: string, isIndex: bool}}>
     */
    public static function fileNameProvider(): array
    {
        return [
            'dash prefix' => [
                '1-getting-started.md',
                ['sortOrder' => 1, 'label' => 'Getting Started', 'isIndex' => false],
            ],
            'underscore prefix' => [
                '02_configuration.md',
                ['sortOrder' => 2, 'label' => 'Configuration', 'isIndex' => false],
            ],
            'no prefix' => [
                'troubleshooting.md',
                ['sortOrder' => 1000, 'label' => 'Troubleshooting', 'isIndex' => false],
            ],
            'index' => [
                'index.md',
                ['sortOrder' => 1000, 'label' => 'Overview', 'isIndex' => true],
            ],
            'prefixed index' => [
                '1-index.md',
                ['sortOrder' => 1, 'label' => 'Overview', 'isIndex' => true],
            ],
            'readme counts as index' => [
                'README.md',
                ['sortOrder' => 1000, 'label' => 'Overview', 'isIndex' => true],
            ],
            'directory name' => [
                '3-advanced',
                ['sortOrder' => 3, 'label' => 'Advanced', 'isIndex' => false],
            ],
            'digits only are not a prefix' => [
                '2024.md',
                ['sortOrder' => 1000, 'label' => '2024', 'isIndex' => false],
            ],
            'dotted directory name keeps its dot' => [
                '2.4-upgrade',
                ['sortOrder' => 1000, 'label' => '2.4 Upgrade', 'isIndex' => false],
            ],
            'dotted markdown file keeps its dot' => [
                'v1.0-intro.md',
                ['sortOrder' => 1000, 'label' => 'V1.0 Intro', 'isIndex' => false],
            ],
            'uppercase extension' => [
                '1-Setup.MD',
                ['sortOrder' => 1, 'label' => 'Setup', 'isIndex' => false],
            ],
        ];
    }
}
