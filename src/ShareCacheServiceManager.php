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

    public function getServers()
    {
        if (!$this->servers) {
            $this->servers = $this->getStore()->get('services') ?: [];
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

    public function getModels()
    {
        $models = array_map(function ($item) {
            return is_array($item) ? $item['class'] : $item;
        }, $this->getConfig('models', []));

        foreach ($this->getConfig('repositories', []) as $repository) {
            if (is_array($repository)) {
                $repository = $repository['class'];
            }
            $models = array_merge($models, Util::getRepositoryProviders($repository));
        }
        return array_unique($models);
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
            $servers = $this->getServers();
            if (empty($servers[$name])) {
                throw new ShareCacheException("share cache server: $name not define.");
            }
            $this->serverInstances[$name] = new ShareCacheService($name, $servers[$name], $this);
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
