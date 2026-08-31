<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model;

use Magebit\Documentation\Api\DocumentationTreeInterface;
use Magento\Framework\App\RequestInterface;

/**
 * Which documentation page the request asks for, falling back to the first one the admin may see.
 */
class CurrentPage
{
    /**
     * @var array{module: string, section: string, path: string}|null
     */
    private ?array $current = null;

    /**
     * @var bool
     */
    private bool $resolved = false;

    /**
     * @param RequestInterface $request
     * @param DocumentationTreeInterface $tree
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly DocumentationTreeInterface $tree
    ) {
    }

    /**
     * The requested page, or nothing when there is no documentation to fall back on.
     *
     * @return array{module: string, section: string, path: string}|null
     */
    public function get(): ?array
    {
        if ($this->resolved) {
            return $this->current;
        }

        $this->resolved = true;

        $module = $this->stringParam('module');
        $section = $this->stringParam('section');
        $path = $this->stringParam('path');

        if ($module !== '' && $section !== '' && $path !== '') {
            return $this->current = ['module' => $module, 'section' => $section, 'path' => $path];
        }

        $first = $this->tree->getFirst();

        if ($first === null) {
            return null;
        }

        return $this->current = [
            'module' => $first['module']->getModuleName(),
            'section' => $first['section']->getName(),
            'path' => $first['page']->getRelativePath(),
        ];
    }

    /**
     * Read one request parameter as a string.
     *
     * Never decoded again: a second decode would turn an encoded "%2e%2e%2f" back into a real "../".
     *
     * @param string $name
     * @return string
     */
    private function stringParam(string $name): string
    {
        $value = $this->request->getParam($name, '');

        return is_string($value) ? trim($value) : '';
    }
}
