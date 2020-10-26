<?php


namespace YiluTech\ShareCache;

class RedisLuaScript
{
    const TAG_SET = <<<'LUA'
redis.replicate_commands()
local timestamp = redis.call('TIME')[1]
local key = KEYS[1]..':'..KEYS[2]
if ARGV[2] == 0 then
    redis.call('SET', key, ARGV[1])
    ARGV[2] = 9999999999
else
    redis.call('SETEX', key, ARGV[2], ARGV[1])
end
return redis.call('ZADD', KEYS[1]..'_keys', timestamp + ARGV[2], key)
LUA;

    const TAG_DEL = <<<'LUA'
local length = table.getn(ARGV)
if length > 0 then
    for i = 1, length do
        ARGV[i] =  KEYS[1]..':'..ARGV[i]
    end
    redis.call('DEL', unpack(ARGV))
    redis.call('ZREM', KEYS[1]..'_keys', unpack(ARGV))
end
return length
LUA;

    const TAG_COUNT = <<<'LUA'
redis.replicate_commands()
local timestamp = redis.call('TIME')[1]
local tag = KEYS[1]..'_keys'
redis.call('ZREMRANGEBYSCORE', tag, 0, timestamp)
return redis.call('ZCARD', tag)
LUA;

    const TAG_FLUSH = <<<'LUA'
redis.replicate_commands()
local timestamp = redis.call('TIME')[1]
local tag = KEYS[1]..'_keys'
redis.call('ZREMRANGEBYSCORE', tag, 0, timestamp)
local index = 0
local size  = 1000
local count = size
while (count == size)
do
    local keys = redis.call('ZRANGE', tag, index, index + size - 1)
    count = table.getn(keys)
    if count > 0 then
        index = index + count
        redis.call('DEL', unpack(keys))
    end
end
redis.call('DEL', tag)
return index
LUA;
}
