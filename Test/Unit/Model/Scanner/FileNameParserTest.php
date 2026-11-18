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

    public function testParseDashPrefix(): void
    {
        $this->assertParse(
            '1-getting-started.md',
            ['sortOrder' => 1, 'label' => 'Getting Started', 'isIndex' => false]
        );
    }

    public function testParseUnderscorePrefix(): void
    {
        $this->assertParse('02_configuration.md', ['sortOrder' => 2, 'label' => 'Configuration', 'isIndex' => false]);
    }

    public function testParseNoPrefix(): void
    {
        $this->assertParse(
            'troubleshooting.md',
            ['sortOrder' => 1000, 'label' => 'Troubleshooting', 'isIndex' => false]
        );
    }

    public function testParseIndex(): void
    {
        $this->assertParse('index.md', ['sortOrder' => 1000, 'label' => 'Overview', 'isIndex' => true]);
    }

    public function testParsePrefixedIndex(): void
    {
        $this->assertParse('1-index.md', ['sortOrder' => 1, 'label' => 'Overview', 'isIndex' => true]);
    }

    public function testParseReadmeCountsAsIndex(): void
    {
        $this->assertParse('README.md', ['sortOrder' => 1000, 'label' => 'Overview', 'isIndex' => true]);
    }

    public function testParseDirectoryName(): void
    {
        $this->assertParse('3-advanced', ['sortOrder' => 3, 'label' => 'Advanced', 'isIndex' => false]);
    }

    public function testParseDigitsOnlyAreNotAPrefix(): void
    {
        $this->assertParse('2024.md', ['sortOrder' => 1000, 'label' => '2024', 'isIndex' => false]);
    }

    public function testParseDottedDirectoryNameKeepsItsDot(): void
    {
        $this->assertParse('2.4-upgrade', ['sortOrder' => 1000, 'label' => '2.4 Upgrade', 'isIndex' => false]);
    }

    public function testParseDottedMarkdownFileKeepsItsDot(): void
    {
        $this->assertParse('v1.0-intro.md', ['sortOrder' => 1000, 'label' => 'V1.0 Intro', 'isIndex' => false]);
    }

    public function testParseUppercaseExtension(): void
    {
        $this->assertParse('1-Setup.MD', ['sortOrder' => 1, 'label' => 'Setup', 'isIndex' => false]);
    }

    /**
     * @param string $fileName
     * @param array{sortOrder: int, label: string, isIndex: bool} $expected
     * @return void
     */
    private function assertParse(string $fileName, array $expected): void
    {
        $this->assertSame($expected, $this->parser->parse($fileName));
    }
}
