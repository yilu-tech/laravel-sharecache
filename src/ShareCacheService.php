<?php

namespace YiluTech\ShareCache;

use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SharedCache
 *
 */
class ShareCacheService
{
    protected $name;

    protected $objects = array();

    protected $config;

    public function __construct($name, $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->objects = $config['objects'];
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $name
     * @param $key
     * @return false|mixed|string|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get($name, $key)
    {
        $value = $this->getCache($name)->get($key);

        if ($value === null) {
            $value = $this->put($name, $key);
        }

        if ($value) {
            try {
                $value = json_decode($value, JSON_OBJECT_AS_ARRAY);
            } catch (\Exception $exception) {
                $value = null;
            }
        }

        return $value;
    }

    public function has($name, $key)
    {
        return $this->getCache($name)->has($key);
    }

    public function put($name, $key)
    {
        if (empty($this->objects[$name])) {
            throw new \Exception("model or repository \"$name\" not define.");
        }
        if ($this->name === ShareCacheServiceManager::getConfig('name')) {
            return $this->callPut($name, $key);
        } else {
            return $this->callRemotePut($name, $key);
        }
    }

    /**
     * @param $model Model
     */
    public function delByModel($model)
    {
        $class = get_class($model);

        foreach ($this->objects as $name => $object) {

            if (($object['type'] === 'model' && $object['class'] === $class) ||
                ($object['type'] === 'repository' && in_array($class, $object['models']))) {

                $this->getCache($object['type'])->flush();

            }
        }
    }

    protected function callPut($name, $key)
    {
        $object = $this->objects[$name];

        $data = $this->getObjectData($object, $key);

        $ttl = ShareCacheServiceManager::getConfig('cache', [])['ttl'] ?? 1209600;

        $this->getCache($object['type'])->put($key, $data, $ttl);

        return $data;
    }

    protected function callRemotePut($name, $key)
    {
        $uri = trim(Util::array_get($this->config, 'url'), " /") . '/sharecache/put';
        try {
            $client = new Client();
            $content = $client->post($uri, [
                'json' => compact('name', 'key'),
                'header' => [
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();
        } catch (\Exception $exception) {
            throw new ShareCacheException('set remote error.');
        }
        if ($content) {
            $content = json_decode($content, JSON_OBJECT_AS_ARRAY);
        }
        return $content;
    }

    /**
     * @param string $object
     * @return \Illuminate\Cache\RedisTaggedCache|\Illuminate\Contracts\Cache\Repository
     * @throws \Exception
     */
    protected function getCache($object)
    {
        if (empty($this->objects[$object])) {
            throw new \Exception("model or repository \"$object\" not define.");
        }
        return ShareCacheServiceManager::getCache([$this->name, $object]);
    }

    /**
     * @param $object
     * @param $key
     * @return false|string|null
     * @throws ShareCacheException
     */
    protected function getObjectData($object, $key)
    {
        if (method_exists($object['class'], 'getShareCache')) {
            $data = $object['class']::getShareCache($key);
            if ($data === null || $data === false) {
                return null;
            }
            if (is_array($data)) {
                $data = json_encode($data);
            }
            if (!is_string($data)) {
                throw new ShareCacheException('model or repository store data type error.');
            }
            return $data;
        }

        if ($object['type'] === 'model') {
            $data = $object['class']::query()->find($key);
            return $data ? $data->toJson() : null;
        }

        throw new ShareCacheException('model or repository serialization function not define.');
    }
}
