<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model;

use Magebit\Documentation\Api\Data\SectionInterface;
use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magebit\Documentation\Api\PageRepositoryInterface;
use Magebit\Documentation\Api\PathResolverInterface;
use Magebit\Documentation\Model\Scanner\FrontMatterReader;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Psr\Log\LoggerInterface;

/**
 * Reads pages of sections the current admin may see, through the path resolver only.
 */
class PageRepository implements PageRepositoryInterface
{
    private const MARKDOWN_EXTENSIONS = ['md'];

    /**
     * @param DocumentationTreeInterface $tree
     * @param PathResolverInterface $pathResolver
     * @param FileDriver $fileDriver
     * @param FrontMatterReader $frontMatterReader
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly DocumentationTreeInterface $tree,
        private readonly PathResolverInterface $pathResolver,
        private readonly FileDriver $fileDriver,
        private readonly FrontMatterReader $frontMatterReader,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContent(string $moduleName, string $sectionName, string $relativePath): ?string
    {
        $section = $this->tree->getSection($moduleName, $sectionName);

        if ($section === null) {
            return null;
        }

        return $this->getContentForSection($moduleName, $section, $relativePath);
    }

    /**
     * @inheritDoc
     */
    public function getContentForSection(
        string $moduleName,
        SectionInterface $section,
        string $relativePath
    ): ?string {
        $absolutePath = $this->resolve($moduleName, $section, $relativePath);

        if ($absolutePath === null) {
            return null;
        }

        try {
            return $this->fileDriver->fileGetContents($absolutePath);
        } catch (FileSystemException $e) {
            $this->logger->warning(
                'Magebit_Documentation could not read a documentation page.',
                ['path' => $absolutePath, 'exception' => $e->getMessage()]
            );

            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getFrontMatter(string $moduleName, string $sectionName, string $relativePath): array
    {
        $section = $this->tree->getSection($moduleName, $sectionName);

        if ($section === null) {
            return [];
        }

        $absolutePath = $this->resolve($moduleName, $section, $relativePath);

        return $absolutePath === null ? [] : $this->frontMatterReader->read($absolutePath);
    }

    /**
     * Turn a section and a request path into an absolute file path, or nothing when refused.
     *
     * A changelog section holds one file named by configuration, so the request path is not used.
     *
     * @param string $moduleName
     * @param SectionInterface $section
     * @param string $relativePath
     * @return string|null
     */
    private function resolve(string $moduleName, SectionInterface $section, string $relativePath): ?string
    {
        if ($section->isChangelog()) {
            $changelog = $this->pathResolver->resolveChangelogFile($moduleName, $section->getPath());

            return $changelog === null ? null : $changelog['path'];
        }

        $sectionRoot = $this->pathResolver->resolveSectionRoot($moduleName, $section->getPath());

        if ($sectionRoot === null) {
            return null;
        }

        return $this->pathResolver->resolveFile($sectionRoot, $relativePath, self::MARKDOWN_EXTENSIONS);
    }
}
