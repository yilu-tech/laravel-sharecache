<?php


namespace YiluTech\ShareCache\Contracts;


interface CacheObject
{
    // public $events
    // public int $ttl = 86400

    public function name(): string;

    public function get();
}
