<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Test\Unit\Model\Scanner;

use Magebit\Documentation\Model\Scanner\FrontMatterReader;
use Magento\Framework\Filesystem\Driver\File;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Parser;

class FrontMatterReaderTest extends TestCase
{
    /**
     * @var string
     */
    private string $root;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var FrontMatterReader
     */
    private FrontMatterReader $reader;

    protected function setUp(): void
    {
        $this->root = (string)realpath(sys_get_temp_dir()) . '/magedoc-front-' . uniqid('', true);
        mkdir($this->root, 0777, true);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->reader = new FrontMatterReader(new File(), new Parser(), $this->logger);
    }

    protected function tearDown(): void
    {
        (new File())->deleteDirectory($this->root);
    }

    public function testReadsTheLeadingBlock(): void
    {
        $this->logger->expects($this->never())->method('warning');

        $path = $this->write('page.md', "---\ntitle: Custom Title\norder: 3\n---\n# Body\n");

        $this->assertSame(['title' => 'Custom Title', 'order' => 3], $this->reader->read($path));
    }

    public function testReadsABlockDelimitedWithWindowsLineEndings(): void
    {
        $path = $this->write('windows.md', "---\r\ntitle: Custom Title\r\n---\r\n# Body\r\n");

        $this->assertSame(['title' => 'Custom Title'], $this->reader->read($path));
    }

    public function testReturnsNothingWhenTheFileHasNoFrontMatter(): void
    {
        $this->logger->expects($this->never())->method('warning');

        $path = $this->write('plain.md', "# Body\n\ntitle: not front matter\n");

        $this->assertSame([], $this->reader->read($path));
    }

    public function testIgnoresABlockThatDoesNotStartTheFile(): void
    {
        $path = $this->write('late.md', "# Body\n\n---\ntitle: Too Late\n---\n");

        $this->assertSame([], $this->reader->read($path));
    }

    public function testReturnsNothingWhenTheBlockIsNotAMapping(): void
    {
        $path = $this->write('scalar.md', "---\njust a scalar\n---\n# Body\n");

        $this->assertSame([], $this->reader->read($path));
    }

    public function testLogsAndReturnsNothingForAMalformedBlock(): void
    {
        $path = $this->write('broken.md', "---\ntitle: \"unclosed\ntags: [a, b\n---\n# Body\n");

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('could not parse the front matter'),
                $this->callback(
                    static fn (mixed $context): bool => is_array($context) && ($context['path'] ?? null) === $path
                )
            );

        $this->assertSame([], $this->reader->read($path));
    }

    public function testIgnoresABlockLargerThanTheReadLimit(): void
    {
        // Keys are unique, so an unbounded read would parse this block instead of ignoring it.
        $padding = '';
        for ($line = 0; $line < 200; $line++) {
            $padding .= 'padding' . $line . ": filler text that pushes the closing delimiter out of reach\n";
        }

        $this->logger->expects($this->never())->method('warning');

        $path = $this->write('huge.md', "---\ntitle: Never Reached\n" . $padding . "---\n# Body\n");

        $this->assertSame([], $this->reader->read($path));
    }

    public function testLogsAndReturnsNothingWhenTheFileCannotBeRead(): void
    {
        $missing = $this->root . '/does-not-exist.md';

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('could not read a documentation page'),
                $this->callback(
                    static fn (mixed $context): bool => is_array($context) && ($context['path'] ?? null) === $missing
                )
            );

        $this->assertSame([], $this->reader->read($missing));
    }

    /**
     * Write a fixture file and return its path.
     *
     * @param string $name
     * @param string $contents
     * @return string
     */
    private function write(string $name, string $contents): string
    {
        $path = $this->root . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }
}
