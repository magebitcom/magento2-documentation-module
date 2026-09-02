<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown\Mermaid;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Renders ```mermaid fences as diagram blocks that the browser draws with the bundled mermaid.js.
 */
class MermaidExtension implements ExtensionInterface
{
    /**
     * Above the core fenced-code renderer at 0, so mermaid fences are claimed first.
     */
    private const PRIORITY = 10;

    /**
     * @inheritDoc
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(FencedCode::class, new MermaidFenceRenderer(), self::PRIORITY);
    }
}
