<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 17:42
 */

namespace YiluTech\ShareCache;


class Util
{
    public static function array_get($array, $name = null, $default = null)
    {
        if ($name === null) {
            return $array;
        }
        return $array[$name] ?? $default;
    }
}
