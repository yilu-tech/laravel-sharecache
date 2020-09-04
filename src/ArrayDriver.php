<?php


namespace YiluTech\ShareCache;


class ArrayDriver
{
    protected $data = [];

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function hget($key, $field)
    {
        return $this->data[$key][$field] ?? null;
    }

    public function hset($key, $field, $value)
    {
        $this->data[$key][$field] = $value;
    }

    public function hdel($key, $field)
    {
        unset($this->data[$key][$field]);
    }

    public function hexists($key, $field)
    {
        return isset($this->data[$key][$field]);
    }

    public function hmget($key, $fields)
    {
        $items = $this->data[$key] ?? [];
        return array_map(function ($field) use ($items) {
            return $items[$field] ?? null;
        }, $fields);
    }

}
