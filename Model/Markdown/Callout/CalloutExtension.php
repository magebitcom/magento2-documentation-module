<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown\Callout;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * GitHub-style callouts: a blockquote opening with [!NOTE], [!TIP], [!IMPORTANT], [!WARNING] or [!CAUTION].
 */
class CalloutExtension implements ExtensionInterface
{
    /**
     * @inheritDoc
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, new CalloutProcessor());
        $environment->addRenderer(Callout::class, new CalloutRenderer());
    }
}
