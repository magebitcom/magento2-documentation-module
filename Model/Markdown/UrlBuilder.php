<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown;

use Magento\Framework\UrlInterface;

/**
 * Builds the admin URLs that rewritten documentation links and images point at.
 */
class UrlBuilder
{
    private const PAGE_ROUTE = 'magebit_documentation/index/index';
    private const ASSET_ROUTE = 'magebit_documentation/asset/index';

    /**
     * @param UrlInterface $url
     */
    public function __construct(private readonly UrlInterface $url)
    {
    }

    /**
     * Build the URL of another documentation page.
     *
     * @param string $module
     * @param string $section
     * @param string $relativePath
     * @return string
     */
    public function page(string $module, string $section, string $relativePath): string
    {
        return $this->build(self::PAGE_ROUTE, $module, $section, $relativePath);
    }

    /**
     * Build the URL of a file that sits next to a documentation page.
     *
     * @param string $module
     * @param string $section
     * @param string $relativePath
     * @return string
     */
    public function asset(string $module, string $section, string $relativePath): string
    {
        return $this->build(self::ASSET_ROUTE, $module, $section, $relativePath);
    }

    /**
     * The path is passed in the query string because route parameters cannot carry slashes.
     *
     * @param string $route
     * @param string $module
     * @param string $section
     * @param string $relativePath
     * @return string
     */
    private function build(string $route, string $module, string $section, string $relativePath): string
    {
        return $this->url->getUrl(
            $route,
            [
                '_query' => [
                    'module' => $module,
                    'section' => $section,
                    'path' => $relativePath,
                ],
            ]
        );
    }
}
