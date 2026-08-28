<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Scanner;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

/**
 * Reads the YAML block between the leading "---" lines of a markdown file.
 */
class FrontMatterReader
{
    /**
     * Only the start of the file is read, so a block never has to fit in memory whole.
     */
    private const MAX_BYTES = 8192;

    /**
     * @param FileDriver $fileDriver
     * @param Parser $yamlParser
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly Parser $yamlParser,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Front matter of a markdown file, empty when it has none or the block is unusable.
     *
     * @param string $absolutePath
     * @return array<string, mixed>
     */
    public function read(string $absolutePath): array
    {
        $head = $this->readHead($absolutePath);

        if ($head === null) {
            return [];
        }

        $block = $this->extractBlock($head);

        if ($block === null) {
            return [];
        }

        try {
            $parsed = $this->yamlParser->parse($block);
        } catch (ParseException $e) {
            $this->logger->warning(
                'Magebit_Documentation could not parse the front matter of a documentation page.',
                ['path' => $absolutePath, 'exception' => $e->getMessage()]
            );

            return [];
        }

        return is_array($parsed) ? $this->withStringKeys($parsed) : [];
    }

    /**
     * Read at most MAX_BYTES from the start of the file.
     *
     * @param string $absolutePath
     * @return string|null
     */
    private function readHead(string $absolutePath): ?string
    {
        try {
            $resource = $this->fileDriver->fileOpen($absolutePath, 'r');
            $head = $this->fileDriver->fileRead($resource, self::MAX_BYTES);
            $this->fileDriver->fileClose($resource);
        } catch (FileSystemException $e) {
            $this->logger->warning(
                'Magebit_Documentation could not read a documentation page.',
                ['path' => $absolutePath, 'exception' => $e->getMessage()]
            );

            return null;
        }

        return $head;
    }

    /**
     * Take the text between the opening and closing delimiter lines.
     *
     * @param string $head
     * @return string|null
     */
    private function extractBlock(string $head): ?string
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|$)/s', $head, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Force the keys to strings, because YAML also allows numeric ones.
     *
     * @param array<array-key,mixed> $parsed
     * @return array<string, mixed>
     */
    private function withStringKeys(array $parsed): array
    {
        $frontMatter = [];

        foreach ($parsed as $key => $value) {
            $frontMatter[(string)$key] = $value;
        }

        return $frontMatter;
    }
}
