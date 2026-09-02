<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown\Mermaid;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * Claims fenced blocks whose language is mermaid; every other fence is passed on by returning null.
 */
class MermaidFenceRenderer implements NodeRendererInterface
{
    public const HOOK_ATTRIBUTE = 'data-doc-mermaid';

    private const LANGUAGE = 'mermaid';

    /**
     * @inheritDoc
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?HtmlElement
    {
        if (!$node instanceof FencedCode || !$this->isMermaid($node)) {
            return null;
        }

        return new HtmlElement(
            'div',
            ['class' => 'doc-diagram', self::HOOK_ATTRIBUTE => true],
            new HtmlElement('pre', ['class' => 'doc-diagram-source'], Xml::escape($node->getLiteral()))
        );
    }

    /**
     * Whether the first word after the opening fence is mermaid.
     *
     * @param FencedCode $node
     * @return bool
     */
    private function isMermaid(FencedCode $node): bool
    {
        $words = $node->getInfoWords();

        return isset($words[0]) && strtolower($words[0]) === self::LANGUAGE;
    }
}
