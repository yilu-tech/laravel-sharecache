<?php


namespace YiluTech\ShareCache;


class RedisLuaScript
{
    const HGETMANY = <<<'LUA'
local items = {}

for k, v in pairs(ARGV) do
    table.insert(items, redis.call('hget', KEYS[1], v))
end

return items
LUA;

}
