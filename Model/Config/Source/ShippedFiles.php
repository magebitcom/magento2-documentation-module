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
     * @var list<string>|null
     */
    private ?array $names = null;

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
     * One option per selectable file, in alphabetical order.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->getSelectableNames() as $name) {
            $options[] = ['value' => $name, 'label' => $this->toLabel($name)];
        }

        return $options;
    }

    /**
     * Every shipped file name, with the suffix cut off.
     *
     * @return list<string>
     */
    public function getNames(): array
    {
        if ($this->names === null) {
            $this->names = $this->readNames();
        }

        return $this->names;
    }

    /**
     * The shipped names an admin may pick from.
     *
     * @return list<string>
     */
    public function getSelectableNames(): array
    {
        return array_values(array_filter($this->getNames(), fn (string $name): bool => $this->isSelectable($name)));
    }

    /**
     * Whether a shipped file is offered as a choice of its own.
     *
     * @param string $name
     * @return bool
     */
    protected function isSelectable(string $name): bool
    {
        return true;
    }

    /**
     * Read the shipped directory once.
     *
     * @return list<string>
     */
    private function readNames(): array
    {
        $modulePath = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);

        if ($modulePath === null || $modulePath === '') {
            return [];
        }

        $directory = rtrim($modulePath, '/') . '/' . $this->getDirectory();
        $suffix = $this->getSuffix();
        $names = [];

        // An install that trimmed the shipped files simply offers no options.
        try {
            foreach ($this->fileDriver->readDirectory($directory) as $path) {
                $fileName = $this->fileName((string)$path);

                if (strlen($fileName) <= strlen($suffix) || !str_ends_with($fileName, $suffix)) {
                    continue;
                }

                if ($this->fileDriver->isDirectory((string)$path)) {
                    continue;
                }

                $names[] = substr($fileName, 0, -strlen($suffix));
            }
        } catch (FileSystemException $e) {
            return [];
        }

        sort($names);

        return $names;
    }

    /**
     * Cut the leading directories off a path the driver returned.
     *
     * The driver always hands back "/"-separated paths, whatever the platform.
     *
     * @param string $path
     * @return string
     */
    private function fileName(string $path): string
    {
        $separator = strrpos($path, '/');

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
