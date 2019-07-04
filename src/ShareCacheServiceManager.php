<?php


namespace YiluTech\ShareCache;

use Illuminate\Support\Facades\Redis;

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

    protected $servers = array();

    protected $serverInstances = array();

    protected static $config;

    public function __construct($app)
    {
        $this->app = $app;
        $this->servers = static::getServers();
    }

    public static function getConfig($name = null, $default = null)
    {
        if (!static::$config) {
            static::$config = app()['config']['sharecache'] ?: [];
        };
        return Util::array_get(static::$config, $name, $default);
    }

    public static function getServers()
    {
        $key = static::getConfig('cache_prefix', 'sharecache') . ':servers';
        if ($servers = Redis::get($key)) {
            return json_decode($servers, JSON_OBJECT_AS_ARRAY);
        }
        return array();
    }

    public static function getModels($config = null)
    {
        if (!$config) {
            $config = static::getConfig();
        }

        return array_unique(array_merge(
            array_values($config['models'] ?? []),
            ...array_values(array_map(function ($repository) {
                return Util::getRepositoryProviders($repository);
            }, $config['repositories'] ?? [])
        )));
    }

    public static function getCachePrefix()
    {
        return static::getConfig('cache_prefix', 'sharecache') . ':server';
    }

    /**
     * @param null $name
     * @return ShareCacheService
     * @throws ShareCacheException
     */
    public function service($name = null)
    {
        if ($name === null) {
            $name = static::getConfig('name');
        }

        if (empty($this->serverInstances[$name])) {
            if (empty($this->servers[$name])) {
                throw new ShareCacheException("share cache server: $name not define.");
            }
            $this->serverInstances[$name] = new ShareCacheService($name, $this->servers[$name]);
        }

        return $this->serverInstances[$name];
    }

    public function __call($name, $arguments)
    {
        return $this->service($name);
    }
}
