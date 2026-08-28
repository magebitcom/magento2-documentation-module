<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\MarkdownConverter;
use Magebit\Documentation\Api\MarkdownRendererInterface;
use Psr\Log\LoggerInterface;

/**
 * Renders one documentation page, with the page it came from bound into the link rewriter.
 */
class Renderer implements MarkdownRendererInterface
{
    /**
     * Runs below the external-link processor, so that processor still sees the original URLs.
     */
    private const REWRITE_PRIORITY = -100;

    /**
     * @param EnvironmentFactory $environmentFactory
     * @param LinkRewriter $linkRewriter
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private readonly EnvironmentFactory $environmentFactory,
        private readonly LinkRewriter $linkRewriter,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function render(string $markdown, array $context): string
    {
        $environment = $this->environmentFactory->create();
        $environment->addEventListener(
            DocumentParsedEvent::class,
            function (DocumentParsedEvent $event) use ($context): void {
                $this->linkRewriter->rewrite($event, $context);
            },
            self::REWRITE_PRIORITY
        );

        try {
            return (new MarkdownConverter($environment))->convert($markdown)->getContent();
        } catch (CommonMarkException $e) {
            $this->logger?->error(
                'Magebit_Documentation could not render a documentation page.',
                ['context' => $context, 'exception' => $e->getMessage()]
            );

            return '';
        }
    }
}
