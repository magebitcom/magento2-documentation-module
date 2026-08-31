<?php

/**
 * @copyright Copyright (c) 2025 Magebit, Ltd. (https://magebit.com/)
 * @author    Magebit <info@magebit.com>
 * @license   MIT
 */

declare(strict_types=1);

namespace Magebit\Documentation\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * The module's own cache type, so it can be cleaned and switched off on its own.
 */
class Type extends TagScope
{
    /**
     * Cache type code, unique among all cache types.
     */
    public const TYPE_IDENTIFIER = 'magebit_documentation';

    /**
     * Cache tag that marks every entry of this type.
     */
    public const CACHE_TAG = 'MAGEBIT_DOCUMENTATION';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
