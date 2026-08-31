<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Builds the CommonMark environment from the DI-declared extension list.
 */
class EnvironmentBuilder
{
    /**
     * Safe defaults, so an environment is never less strict than this even when nothing is configured.
     * etc/di.xml deliberately repeats the first three so integrators can see and override them.
     *
     * @var array<string,mixed>
     */
    private const DEFAULT_CONFIG = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 50,
        'external_link' => [
            'open_in_new_window' => true,
            'nofollow' => 'external',
            'noopener' => 'external',
            'noreferrer' => 'external',
        ],
    ];

    /**
     * @param array<string,ExtensionInterface> $extensions
     * @param array<string,mixed> $config
     */
    public function __construct(
        private readonly array $extensions = [],
        private readonly array $config = []
    ) {
    }

    /**
     * Build an environment that no other render shares, with the core extension always in place.
     *
     * @return Environment
     */
    public function create(): Environment
    {
        $environment = new Environment($this->mergedConfig());
        $environment->addExtension(new CommonMarkCoreExtension());

        foreach ($this->extensions as $extension) {
            $environment->addExtension($extension);
        }

        return $environment;
    }

    /**
     * Defaults plus the configured overrides, with the nesting limit made an int again.
     *
     * A di.xml argument always reaches PHP as a string, which the CommonMark config refuses.
     *
     * @return array<string,mixed>
     */
    private function mergedConfig(): array
    {
        /** @var array<string,mixed> $config */
        $config = array_replace_recursive(self::DEFAULT_CONFIG, $this->config);

        $nestingLevel = $config['max_nesting_level'] ?? null;
        if (is_numeric($nestingLevel)) {
            $config['max_nesting_level'] = (int)$nestingLevel;
        }

        return $config;
    }
}
