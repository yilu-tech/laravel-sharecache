<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 17:42
 */

namespace YiluTech\ShareCache;


use Illuminate\Database\Eloquent\Model;

class Util
{
    public static function getRepositoryProviders($repository)
    {
        $reflection = new \ReflectionClass($repository);

        if (!$reflection->hasMethod('__construct')) {
            return [];
        }

        $reflectionMethod = $reflection->getMethod('__construct');

        $models = array();

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type && is_subclass_of($type->getName(), Model::class)) {
                $models[] = $type->getName();
            }
        }

        return $models;
    }
}
