<?php


namespace YiluTech\ShareCache;


use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Redis\Factory as Redis;


class RedisStore implements Store
{
    /**
     * The Redis factory implementation.
     *
     * @var \Illuminate\Contracts\Redis\Factory | \Predis\Client
     */
    protected $redis;

    protected $prefix;

    protected $tag;

    protected $connection;

    public function __construct(Redis $redis, $prefix = '', $connection = 'default', $tag = null)
    {
        $this->redis = $redis;
        $this->tag = $tag;

        $this->setPrefix($prefix);
        $this->setConnection($connection);
    }

    public function tags($names)
    {
        return new static($this->redis, rtrim($this->prefix, ':'), $this->connection, $names);
    }

    public function setPrefix($prefix)
    {
        $this->prefix = !empty($prefix) ? $prefix . ':' : '';
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function put($key, $value, $seconds)
    {
        if ($this->tag) {
            return $this->connection()->eval(RedisLuaScript::TAG_SET, 2, $this->prefix . $this->tag, $key, $this->serialize($value), $seconds);
        }
        if ($seconds) {
            return $this->connection()->setex($this->prefix . $key, $seconds, $this->serialize($value));
        }
        return $this->connection()->set($this->prefix . $key, $this->serialize($value));
    }

    public function putMany(array $values, $seconds)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }
    }

    public function get($key)
    {
        if ($this->tag) {
            $key = $this->tag . ':' . $key;
        }
        return $this->unserialize($this->connection()->get($this->prefix . $key));
    }

    public function many(array $keys)
    {
        $mapper = $this->tag ? function ($key) {
            return $this->prefix . $this->tag . ':' . $key;
        } : function ($key) {
            return $this->prefix . $key;
        };

        $result = $this->connection()->mget(array_map($mapper, $keys));

        return array_combine($keys, array_map([$this, 'unserialize'], $result));
    }

    public function has($key)
    {
        if ($this->tag) {
            $key = $this->tag . ':' . $key;
        }
        return $this->connection()->exists($this->prefix . $key);
    }

    public function forget($key)
    {
        if (!is_array($key)) {
            $key = [$key];
        }
        if ($this->tag) {
            return $this->connection()->eval(RedisLuaScript::TAG_DEL, 1, $this->prefix . $this->tag, ...$key);
        }
        return $this->connection()->del(array_map(function ($key) {
            return $this->prefix . $key;
        }, $key));
    }

    public function increment($key, $value = 1)
    {
        if ($this->tag) {
            $key = $this->tag . ':' . $key;
        }
        return $this->connection()->incrby($this->prefix . $key, $value);
    }

    public function decrement($key, $value = 1)
    {
        if ($this->tag) {
            $key = $this->tag . ':' . $key;
        }
        return $this->connection()->decrby($this->prefix . $key, $value);
    }

    public function flush()
    {
        if ($this->tag) {
            return $this->connection()->eval(RedisLuaScript::TAG_FLUSH, 1, $this->prefix . $this->tag);
        }
        return $this->connection()->flushdb();
    }

    public function forever($key, $value)
    {
        if ($this->tag) {
            return $this->put($key, $value, 0);
        }
        return $this->connection()->set($this->prefix . $key, $this->serialize($value));
    }

    public function count()
    {
        if ($this->tag) {
            return $this->connection()->eval(RedisLuaScript::TAG_COUNT, 1, $this->prefix . $this->tag);
        }
        return $this->connection()->dbsize();
    }

    public function connection()
    {
        return $this->redis->connection($this->connection);
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    protected function serialize($value)
    {
        return is_numeric($value) ? $value : json_encode($value);
    }

    protected function unserialize($value)
    {
        try {
            return is_numeric($value) ? $value : json_decode($value, true);
        } catch (\Exception $exception) {
            return null;
        }
    }
}
