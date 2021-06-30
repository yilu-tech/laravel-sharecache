<?php


namespace YiluTech\ShareCache\Contracts;


interface CacheMap
{
    // public $events
    // public int $ttl = 86400

    public function name(): string;

    public function get($key);

    public function getMany(array $keys): array;
}
