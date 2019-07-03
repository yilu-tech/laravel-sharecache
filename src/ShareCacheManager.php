<?php


namespace YiluTech\ShareCache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

/**
 * Class SharedCache
 *
 */
class ShareCacheManager
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    protected $servers = array();

    /**
     * @var ShareCacheServer
     */
    protected $server;

    protected $serverInstances = array();

    protected static $config;

    public function __construct($app)
    {
        $this->app = $app;

        $this->server = new ShareCacheServer(static::getConfig());

        $this->serverInstances[$this->server->getName()] = $this->server;

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
        return array_merge(
            $config['models'] ?? [],
            array_filter(array_map(function ($repository) {
                return ShareCacheManager::getRepositoryModel($repository);
            }, $config['repositories'] ?? []))
        );
    }

    protected static function getRepositoryModel(string $repository)
    {
        $reflection = new \ReflectionClass($repository);
        if (!$reflection->hasMethod('__construct')) {
            return null;
        }
        $reflectionMethod = $reflection->getMethod('__construct');
        foreach ($reflectionMethod->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type && is_subclass_of($type->getName(), Model::class)) {
                return $type->getName();
            }
        }
        return null;
    }

    public static function getModelName($model)
    {
        return array_search($model, static::getConfig('models', []), true);
    }

    /**
     * @param null $name
     * @return ShareCacheServer|mixed
     * @throws ShareCacheException
     */
    public function getServer($name = null)
    {
        if ($name === null) {
            return $this->server;
        }
        if (empty($this->serverInstances[$name])) {
            if (empty($this->server[$name])) {
                throw new ShareCacheException("share cache server: $name not define.");
            }
            $this->serverInstances[$name] = new ShareCacheServer($this->servers[$name]);
        }
        return $this->serverInstances[$name];
    }

    public function __call($name, $arguments)
    {
        return $this->getServer($name);
    }
}
