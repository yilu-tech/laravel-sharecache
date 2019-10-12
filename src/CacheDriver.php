<?php


namespace YiluTech\ShareCache;

use Illuminate\Support\Facades\Redis;

class CacheDriver
{
    protected $config;

    /**
     * @var \Predis\Client
     */
    protected $driver;

    protected $prefix;

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->setPrefix($this->config['prefix'] ?? 'sharecache');
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix . ':';
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function getDriver()
    {
        if (!$this->driver) {
            $this->driver = Redis::connection();
        }
        return $this->driver;
    }

    public function __call($name, $arguments)
    {
        if (is_array($arguments[0])) {
            $arguments[0] = array_map(function ($key) {
                return $this->prefix . $key;
            }, $arguments[0]);
        } else {
            $arguments[0] = $this->prefix . $arguments[0];
        }
        return $this->getDriver()->{$name}(...$arguments);
    }
}
