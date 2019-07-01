<?php

namespace YiluTech\ShareCache\Facade;

use YiluTech\ShareCache\ShareCacheManager;
use Illuminate\Support\Facades\Facade;

/**
 * Class ShareCache
 *
 */
class ShareCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ShareCacheManager::class;
    }
}
