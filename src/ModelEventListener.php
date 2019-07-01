<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 14:19
 */

namespace YiluTech\ShareCache;


class ModelEventListener
{
    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function saved($model)
    {
        if ($name = ShareCacheManager::getModelName(get_class($model))) {
            app(ShareCacheManager::class)->getServer()->setModel($name, $model);
        }
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function created($model)
    {
        static::saved($model);
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function updated($model)
    {
        static::saved($model);
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function deleted($model)
    {
        if ($name = ShareCacheManager::getModelName(get_class($model))) {
            app(ShareCacheManager::class)->getServer()->delModel($name, $model);
        }
    }
}
