<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Markdown\Callout;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A blockquote that opened with a GitHub-style marker such as [!NOTE].
 */
class Callout extends AbstractBlock
{
    public const NOTE = 'note';
    public const TIP = 'tip';
    public const IMPORTANT = 'important';
    public const WARNING = 'warning';
    public const CAUTION = 'caution';

    public const TYPES = [self::NOTE, self::TIP, self::IMPORTANT, self::WARNING, self::CAUTION];

    /**
     * @param string $type One of the TYPES constants.
     */
    public function __construct(private readonly string $type)
    {
        parent::__construct();
    }

    /**
     * Which marker opened the callout, lower case.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
