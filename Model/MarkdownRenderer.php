<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Magebit\Documentation\Api\MarkdownRendererInterface;
use Magento\Framework\UrlInterface;

class MarkdownRenderer implements MarkdownRendererInterface
{
    private ?GithubFlavoredMarkdownConverter $converter = null;

    /**
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Get or create the markdown converter instance
     *
     * @return GithubFlavoredMarkdownConverter
     */
    private function getConverter(): GithubFlavoredMarkdownConverter
    {
        if ($this->converter === null) {
            $this->converter = new GithubFlavoredMarkdownConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return $this->converter;
    }

    /**
     * @inheritDoc
     */
    public function render(string $markdown, array $context = []): string
    {
        try {
            $html = $this->getConverter()->convert($markdown)->getContent();
            return $this->processLinks($html, $context);
        } catch (CommonMarkException $e) {
            return '<p>Error rendering documentation.</p>';
        }
    }

    /**
     * Process links in rendered HTML
     *
     * @param string $html
     * @param array<string, string> $context
     * @return string
     */
    private function processLinks(string $html, array $context): string
    {
        $module = $context['module'] ?? '';
        $feature = $context['feature'] ?? '';
        $currentFile = $context['file'] ?? '';

        // Get current directory from file path
        $currentDir = dirname($currentFile);
        if ($currentDir === '.') {
            $currentDir = '';
        }

        // Process all href attributes
        return preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) use ($module, $feature, $currentDir) {
                $beforeHref = $matches[1];
                $href = $matches[2];
                $afterHref = $matches[3];

                $newHref = $this->resolveLink($href, $module, $feature, $currentDir);

                // Add target="_blank" for external links
                $extraAttrs = '';
                if ($this->isExternalLink($href)) {
                    if (stripos($beforeHref . $afterHref, 'target=') === false) {
                        $extraAttrs = ' target="_blank" rel="noopener noreferrer"';
                    }
                }

                return '<a ' . $beforeHref . 'href="' . htmlspecialchars($newHref) . '"' . $afterHref . $extraAttrs . '>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Resolve a link to its proper URL
     *
     * @param string $href
     * @param string $module
     * @param string $feature
     * @param string $currentDir
     * @return string
     */
    private function resolveLink(string $href, string $module, string $feature, string $currentDir): string
    {
        // External links - keep as-is
        if ($this->isExternalLink($href)) {
            return $href;
        }

        // Absolute paths starting with / - keep as-is (admin links, etc.)
        if (str_starts_with($href, '/')) {
            return $href;
        }

        // Anchor links - keep as-is
        if (str_starts_with($href, '#')) {
            return $href;
        }

        // Mailto/tel links - keep as-is
        if (preg_match('/^(mailto|tel):/i', $href)) {
            return $href;
        }

        // Relative markdown file links
        if (str_ends_with(strtolower($href), '.md')) {
            return $this->buildDocumentationUrl($href, $module, $feature, $currentDir);
        }

        // Other relative links - try to resolve as documentation
        if (!str_contains($href, '://') && !str_contains($href, ':')) {
            // Could be a doc link without extension
            return $this->buildDocumentationUrl($href . '.md', $module, $feature, $currentDir);
        }

        return $href;
    }

    /**
     * Check if link is external
     *
     * @param string $href
     * @return bool
     */
    private function isExternalLink(string $href): bool
    {
        return (bool) preg_match('/^https?:\/\//i', $href);
    }

    /**
     * Build documentation URL for a relative file path
     *
     * @param string $relativePath
     * @param string $module
     * @param string $feature
     * @param string $currentDir
     * @return string
     */
    private function buildDocumentationUrl(
        string $relativePath,
        string $module,
        string $feature,
        string $currentDir
    ): string {
        // Handle relative paths like "../other.md" or "./file.md"
        if ($currentDir !== '' && !str_starts_with($relativePath, '/')) {
            $relativePath = $currentDir . '/' . $relativePath;
        }

        // Normalize the path (resolve ../ and ./)
        $relativePath = $this->normalizePath($relativePath);

        return $this->urlBuilder->getUrl('magebit_documentation/index/index', [
            'module' => $module,
            'feature' => $feature,
            'file' => $relativePath,
        ]);
    }

    /**
     * Normalize a file path (resolve . and ..)
     *
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $parts = [];
        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
            } else {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }
}
