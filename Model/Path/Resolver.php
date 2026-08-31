<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Path;

use Magebit\Documentation\Api\PathResolverInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

/**
 * The only place in the module that turns a configured path into a disk path.
 */
class Resolver implements PathResolverInterface
{
    /**
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param FileDriver $fileDriver
     */
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly FileDriver $fileDriver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolveSectionRoot(string $contextModule, string $configuredPath): ?string
    {
        if ($configuredPath === '' || str_contains($configuredPath, "\0")) {
            return null;
        }

        [$targetModule, $relativePath] = $this->splitModulePath($contextModule, $configuredPath);
        if ($relativePath === '') {
            return null;
        }

        $modulePath = $this->moduleDirectory($targetModule);
        if ($modulePath === null) {
            return null;
        }

        $candidate = $this->within($modulePath, $relativePath);
        if ($candidate === null || !$this->fileDriver->isDirectory($candidate)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @inheritDoc
     */
    public function resolveFile(string $sectionRoot, string $relativePath, array $allowedExtensions): ?string
    {
        $candidate = $this->within($sectionRoot, $relativePath);
        if ($candidate === null) {
            return null;
        }

        if (!$this->hasAllowedExtension($candidate, $allowedExtensions)) {
            return null;
        }

        if (!$this->fileDriver->isFile($candidate) || !$this->fileDriver->isReadable($candidate)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @inheritDoc
     */
    public function resolveChangelogFile(string $contextModule, string $configuredPath): ?array
    {
        if ($configuredPath === '' || str_contains($configuredPath, "\0")) {
            return null;
        }

        [$targetModule, $relativePath] = $this->splitModulePath($contextModule, $configuredPath);
        if ($relativePath === '') {
            return null;
        }

        $modulePath = $this->moduleDirectory($targetModule);
        if ($modulePath === null) {
            return null;
        }

        $absolutePath = $this->resolveFile($modulePath, $relativePath, ['md']);

        if ($absolutePath === null) {
            return null;
        }

        return ['path' => $absolutePath, 'fileName' => $this->fileName($absolutePath)];
    }

    /**
     * Take the bare file name off the end of a path.
     *
     * @param string $path
     * @return string
     */
    private function fileName(string $path): string
    {
        $separator = strrpos($path, DIRECTORY_SEPARATOR);

        return $separator === false ? $path : substr($path, $separator + 1);
    }

    /**
     * Split "Vendor_B::Docs" into its module and its path, falling back to the declaring module.
     *
     * @param string $contextModule
     * @param string $configuredPath
     * @return array{string, string}
     */
    private function splitModulePath(string $contextModule, string $configuredPath): array
    {
        if (str_contains($configuredPath, '::')) {
            [$targetModule, $relativePath] = explode('::', $configuredPath, 2);

            return [$targetModule, $relativePath];
        }

        return [$contextModule, $configuredPath];
    }

    /**
     * Look up where a module lives on disk.
     *
     * @param string $moduleName
     * @return string|null
     */
    private function moduleDirectory(string $moduleName): ?string
    {
        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);

        return $modulePath === null || $modulePath === '' ? null : $modulePath;
    }

    /**
     * Resolve a child path and confirm it did not escape its parent.
     *
     * @param string $parent
     * @param string $child
     * @return string|null
     */
    private function within(string $parent, string $child): ?string
    {
        if (str_contains($child, "\0")) {
            return null;
        }

        try {
            $realParent = $this->fileDriver->getRealPath($parent);
            $realChild = $this->fileDriver->getRealPath(rtrim($parent, '/') . '/' . ltrim($child, '/'));
        } catch (FileSystemException $e) {
            return null;
        }

        if (!is_string($realParent) || !is_string($realChild)) {
            return null;
        }

        $boundary = rtrim($realParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($realChild !== rtrim($realParent, DIRECTORY_SEPARATOR)
            && !str_starts_with($realChild, $boundary)
        ) {
            return null;
        }

        return $realChild;
    }

    /**
     * Check the file ending against the whitelist, ignoring letter case.
     *
     * @param string $path
     * @param list<string> $allowedExtensions
     * @return bool
     */
    private function hasAllowedExtension(string $path, array $allowedExtensions): bool
    {
        $fileName = $this->fileName($path);

        $dot = strrpos($fileName, '.');
        if ($dot === false) {
            return false;
        }

        $extension = strtolower(substr($fileName, $dot + 1));

        return in_array($extension, array_map('strtolower', $allowedExtensions), true);
    }
}
