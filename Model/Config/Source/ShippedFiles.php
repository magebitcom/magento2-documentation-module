<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Config\Source;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

/**
 * Builds config options out of the file names the module actually ships.
 */
abstract class ShippedFiles implements OptionSourceInterface
{
    private const MODULE_NAME = 'Magebit_Documentation';

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
     * Directory holding the files, relative to the module root.
     *
     * @return string
     */
    abstract protected function getDirectory(): string;

    /**
     * File name ending that marks a file as an option, and is cut off to make its value.
     *
     * @return string
     */
    abstract protected function getSuffix(): string;

    /**
     * One option per shipped file, in alphabetical order.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->getNames() as $name) {
            $options[] = ['value' => $name, 'label' => $this->toLabel($name)];
        }

        return $options;
    }

    /**
     * File names in the shipped directory, with the suffix cut off.
     *
     * @return list<string>
     */
    private function getNames(): array
    {
        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);

        if ($modulePath === null || $modulePath === '') {
            return [];
        }

        $directory = rtrim($modulePath, '/') . '/' . $this->getDirectory();

        // An install that trimmed the shipped files simply offers no options.
        try {
            $paths = $this->fileDriver->readDirectory($directory);
        } catch (FileSystemException $e) {
            return [];
        }

        $suffix = $this->getSuffix();
        $names = [];

        foreach ($paths as $path) {
            $fileName = $this->fileName((string)$path);

            if (strlen($fileName) > strlen($suffix) && str_ends_with($fileName, $suffix)) {
                $names[] = substr($fileName, 0, -strlen($suffix));
            }
        }

        sort($names);

        return $names;
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
     * Turn "github-dark" into "Github Dark" for the admin select.
     *
     * @param string $name
     * @return string
     */
    private function toLabel(string $name): string
    {
        return ucwords(str_replace('-', ' ', $name));
    }
}
