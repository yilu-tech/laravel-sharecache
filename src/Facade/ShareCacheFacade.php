<?php

namespace YiluTech\ShareCache\Facade;

use YiluTech\ShareCache\ShareCacheServiceManager;
use Illuminate\Support\Facades\Facade;

/**
 * Class ShareCache
 * @method static \YiluTech\ShareCache\ShareCacheService service($name = null)
 * @method static \YiluTech\ShareCache\ShareCacheService | \YiluTech\ShareCache\ShareCacheObject | array | null get($server = null, $object = null, $key = null)
 */
class ShareCache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ShareCacheServiceManager::class;
    }
}
