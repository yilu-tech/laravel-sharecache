<?php


namespace YiluTech\ShareCache;


class RedisStore extends \Illuminate\Cache\RedisStore
{
    /**
     * Serialize the value.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) ? $value : json_encode($value);
    }

    /**
     * Unserialize the value.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        try {
            return is_numeric($value) ? $value : json_decode($value, JSON_OBJECT_AS_ARRAY);
        } catch (\Exception $exception) {
            return null;
        }
    }
}
