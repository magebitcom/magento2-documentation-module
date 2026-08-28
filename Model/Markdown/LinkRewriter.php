<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

/**
 * Points relative links and images at the documentation controllers, working on the parsed document only.
 */
class LinkRewriter
{
    private const ABSOLUTE_PATTERN = '#^(?:[a-z][a-z0-9+.-]*:|//|/|\#)#i';

    /**
     * @param UrlBuilder $urlBuilder
     */
    public function __construct(private readonly UrlBuilder $urlBuilder)
    {
    }

    /**
     * Rewrite relative document and asset URLs in a parsed document.
     *
     * @param DocumentParsedEvent $event
     * @param array{module:string,section:string,path:string} $context
     * @return void
     */
    public function rewrite(DocumentParsedEvent $event, array $context): void
    {
        $currentDir = $this->directoryOf($context['path']);
        $walker = $event->getDocument()->walker();

        while ($walkerEvent = $walker->next()) {
            $node = $walkerEvent->getNode();

            if (!$walkerEvent->isEntering() || (!$node instanceof Link && !$node instanceof Image)) {
                continue;
            }

            $url = $node->getUrl();
            if ($url === '' || $this->isAbsolute($url)) {
                continue;
            }

            [$path, $fragment] = $this->splitPath($url);
            $resolved = $this->resolve($currentDir, $path);

            if ($node instanceof Image) {
                $node->setUrl(
                    $this->urlBuilder->asset($context['module'], $context['section'], $resolved) . $fragment
                );
                continue;
            }

            if (str_ends_with(strtolower($path), '.md')) {
                $node->setUrl(
                    $this->urlBuilder->page($context['module'], $context['section'], $resolved) . $fragment
                );
            }
        }
    }

    /**
     * Split a URL into the file path and the "#..." that follows it.
     *
     * The query is dropped: the URL we build carries its own, and an author parameter named
     * "path" would otherwise override the one we just resolved.
     *
     * @param string $url
     * @return array{string, string}
     */
    private function splitPath(string $url): array
    {
        $fragment = '';

        $hashAt = strpos($url, '#');
        if ($hashAt !== false) {
            $fragment = substr($url, $hashAt);
            $url = substr($url, 0, $hashAt);
        }

        $queryAt = strpos($url, '?');
        if ($queryAt !== false) {
            $url = substr($url, 0, $queryAt);
        }

        return [$url, $fragment];
    }

    /**
     * Turn a link into the path of the file it points at, decoding each segment first.
     *
     * Normalising last is what keeps an encoded "%2e%2e%2f" from climbing out of the section.
     *
     * @param string $currentDir
     * @param string $path
     * @return string
     */
    private function resolve(string $currentDir, string $path): string
    {
        $decoded = array_map('rawurldecode', explode('/', $currentDir . $path));

        return $this->normalize(implode('/', $decoded));
    }

    /**
     * The directory the page lives in, with its trailing slash, or an empty string at the section root.
     *
     * @param string $path
     * @return string
     */
    private function directoryOf(string $path): string
    {
        $lastSlash = strrpos($path, '/');

        return $lastSlash === false ? '' : substr($path, 0, $lastSlash + 1);
    }

    /**
     * Is the URL already pointing somewhere on its own, rather than at a file next to the page?
     *
     * @param string $url
     * @return bool
     */
    private function isAbsolute(string $url): bool
    {
        return preg_match(self::ABSOLUTE_PATTERN, $url) === 1;
    }

    /**
     * Collapse "." and ".." segments, clamping at the section root so a link can never climb out of it.
     *
     * @param string $path
     * @return string
     */
    private function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
