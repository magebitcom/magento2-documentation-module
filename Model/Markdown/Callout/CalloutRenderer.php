<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown\Callout;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

class CalloutRenderer implements NodeRendererInterface
{
    /**
     * @inheritDoc
     *
     * @throws \InvalidArgumentException
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (!$node instanceof Callout) {
            throw new \InvalidArgumentException('Incompatible node type: ' . get_class($node));
        }

        $separator = $childRenderer->getBlockSeparator();
        $title = new HtmlElement(
            'p',
            ['class' => 'doc-callout-title'],
            Xml::escape($this->title($node->getType()))
        );

        return new HtmlElement(
            'aside',
            ['class' => 'doc-callout _' . $node->getType()],
            $separator . $title . $separator . $childRenderer->renderNodes($node->children()) . $separator
        );
    }

    /**
     * The translated heading shown at the top of the box.
     *
     * @param string $type
     * @return string
     */
    private function title(string $type): string
    {
        return match ($type) {
            Callout::TIP => (string)__('Tip'),
            Callout::IMPORTANT => (string)__('Important'),
            Callout::WARNING => (string)__('Warning'),
            Callout::CAUTION => (string)__('Caution'),
            default => (string)__('Note'),
        };
    }
}
