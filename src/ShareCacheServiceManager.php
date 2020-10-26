<?php

namespace YiluTech\ShareCache;

use Illuminate\Cache\ArrayStore;


class ShareCacheServiceManager
{
    protected $servers;

    protected $config;

    protected $prefix;

    protected $serverInstances = array();

    protected $mocking = false;

    protected $store;

    public function __construct($config = array())
    {
        $this->config = $config;
        $this->setPrefix($this->getConfig('prefix', 'sharecache'));
    }

    public function setPrefix($name)
    {
        $this->prefix = trim($name, ':');
        return $this;
    }

    public function cacheable()
    {
        return !empty($this->config['objects']) || !empty($this->config['repositories']);
    }

    public function getServers($name = false)
    {
        if (!$this->servers) {
            $this->servers = $this->getStore()->get('services') ?: [];
        }

        if ($name !== false) {
            if (!empty($name)) {
                return $this->servers[$name] ?? null;
            }
            if (!$this->cacheable()) {
                return null;
            }
            return $this->servers[$this->getConfig('name')] ?? null;
        }
        return $this->servers;
    }

    public function setServers(array $servers)
    {
        $this->servers = $servers;
        $this->getStore()->forever('services', $servers);
        return $this;
    }

    public function isCurrentServer($name)
    {
        return $this->mocking || $this->config['name'] == $name;
    }

    public function mock(array $servers)
    {
        $this->mocking = true;
        $this->store = resolve(ArrayStore::class);
        return $this->setServers($servers);
    }

    public function mocking()
    {
        return $this->mocking;
    }

    public function getStore()
    {
        if (!$this->store) {
            $this->store = tap(resolve(RedisStore::class), function ($store) {
                $store->setPrefix($this->prefix);
                $store->setConnection($this->getConfig('database', 'default'));
            });
        }
        return $this->store;
    }

    public function getConfig($name = null, $default = null)
    {
        if ($name === null) {
            return $this->config;
        }
        return $this->config[$name] ?? $default;
    }

    /**
     * @param null $name
     * @return ShareCacheService
     * @throws ShareCacheException
     */
    public function service($name = null)
    {
        if ($name === null) {
            $name = $this->getConfig('name');
        }

        if (empty($this->serverInstances[$name])) {
            if (empty($config = $this->getServers($name))) {
                throw new ShareCacheException("Share cache server[$name] undefined.");
            }
            $this->serverInstances[$name] = new ShareCacheService($name, $config, $this);
        }

        return $this->serverInstances[$name];
    }

    public function __call($name, $arguments)
    {
        if (count($arguments)) {
            $service = $this->service(array_shift($arguments));
            return $service->{$name}(...$arguments);
        }
        return $this->service($name);
    }
}
