<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\MarkdownConverter;
use Magebit\Documentation\Api\MarkdownRendererInterface;
use Psr\Log\LoggerInterface;

/**
 * Renders one documentation page, with the page it came from bound into the link rewriter.
 */
class Renderer implements MarkdownRendererInterface
{
    /**
     * Below the external-link processor at -50, so that one still sees the original URLs,
     * and clear of the heading-permalink processor at -100.
     */
    private const REWRITE_PRIORITY = -75;

    /**
     * @param EnvironmentBuilder $environmentBuilder
     * @param LinkRewriter $linkRewriter
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly EnvironmentBuilder $environmentBuilder,
        private readonly LinkRewriter $linkRewriter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function render(string $markdown, array $context): string
    {
        $environment = $this->environmentBuilder->create();
        $environment->addEventListener(
            DocumentParsedEvent::class,
            function (DocumentParsedEvent $event) use ($context): void {
                $this->linkRewriter->rewrite($event, $context);
            },
            self::REWRITE_PRIORITY
        );

        // Everything is caught: bad markdown, a misconfigured environment and URL generation
        // all run inside convert(), and one bad page must not take the admin down.
        try {
            return (new MarkdownConverter($environment))->convert($markdown)->getContent();
        } catch (\Throwable $e) {
            $this->logger->error(
                'Magebit_Documentation could not render a documentation page.',
                ['context' => $context, 'exception' => $e]
            );

            return '';
        }
    }
}
