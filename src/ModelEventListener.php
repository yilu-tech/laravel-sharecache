<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 14:19
 */

namespace YiluTech\ShareCache;

use YiluTech\ShareCache\Facade\ShareCache;

class ModelEventListener
{
    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function saved($model)
    {
        static::deleted($model);
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function created($model)
    {
        static::deleted($model);
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function updated($model)
    {
        static::deleted($model);
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     */
    public static function deleted($model)
    {
        ShareCache::service()->delByModel($model);
    }
}
