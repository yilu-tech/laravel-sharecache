<?php

namespace YiluTech\ShareCache;

/**
 * Class SharedCache
 *
 */
class ShareCacheServiceManager
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    protected $servers;

    /**
     * @var \Predis\Client
     */
    protected $driver;

    protected $config;

    protected $serverInstances = array();

    public function __construct($config = array())
    {
        $this->config = $config;
    }

    public function getServers()
    {
        if (!$this->servers) {
            $this->servers = json_decode($this->getDriver()->get('servers'), JSON_OBJECT_AS_ARRAY) ?: [];
        }
        return $this->servers;
    }

    public function setServers(array $servers)
    {
        $this->servers = $servers;
        $this->getDriver()->set('servers', json_encode($servers));
        return $this;
    }

    public function getDriver()
    {
        if (!$this->driver) {
            $this->driver = new CacheDriver($config['cache'] ?? []);
        }
        return $this->driver;
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
        $models = $this->getConfig('models', []);
        foreach ($this->getConfig('repositories', []) as $repository) {
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
        $servers = $this->getServers();

        if (empty($this->serverInstances[$name])) {
            if (empty($servers[$name])) {
                throw new ShareCacheException("share cache server: $name not define.");
            }
            $this->serverInstances[$name] = new ShareCacheService($name, $servers[$name], $this);
        }

        return $this->serverInstances[$name];
    }

    public function __call($fun, $arguments)
    {
        $name = array_shift($arguments);
        return $this->service($name)->{$fun}(...$arguments);
    }
}
