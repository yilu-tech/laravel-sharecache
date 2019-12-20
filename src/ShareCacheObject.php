<?php

namespace YiluTech\ShareCache;

use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SharedCache
 *
 */
class ShareCacheObject
{
    protected $name;

    protected $config;

    /**
     * @var ShareCacheService
     */
    protected $service;

    public function __construct($name, array $config, ShareCacheService $service)
    {
        $this->name = $name;
        $this->config = $config;

        $this->service = $service;
    }

    public function getName()
    {
        $name = $this->service->getName() . ':' . $this->name;
        return $this->service->getManager()->applyPrefix($name);
    }

    public function driver()
    {
        return $this->service->getManager()->getDriver();
    }

    public function get($key)
    {
        if (is_array($key)) {
            return $this->getMany($key);
        }

        $value = $this->driver()->hget($this->getName(), $key);

        if ($value === null) {
            $value = $this->put($key);
        }

        return $this->format($value);
    }

    public function getMany($keys)
    {
        $keys = array_values($keys);
        $values = $this->driver()->hmget($this->getName(), $keys);

        foreach ($values as $key => &$value) {
            if ($value === null) {
                $value = $this->put($keys[$key]);
            }
            $value = $this->format($value);
        }
        return array_combine($keys, $values);
    }

    public function has($key)
    {
        return $this->driver()->hexists($this->getName(), $key);
    }

    public function put($key)
    {
        return $this->service->isRemote()
            ? $this->remoteSet($key)
            : $this->localSet($key);
    }

    protected function localSet($key)
    {
        $data = $this->getObjectData($key);

        if ($data) {
            $this->driver()->hset($this->getName(), $key, $data);
        }

        return $data;
    }

    protected function remoteSet($key)
    {
        try {
            $uri = $this->service->getUrl() . '/sharecache/put';
            $content = (new Client())->post($uri, [
                'json' => [
                    'name' => $this->name,
                    'key' => $key
                ],
                'header' => [
                    'Accept' => 'application/json'
                ]
            ])->getBody()->getContents();
        } catch (\Exception $exception) {
            throw new ShareCacheException('set remote error.');
        }
        return $content;
    }

    /**
     * @param $key
     * @return false|string|null
     * @throws ShareCacheException
     */
    protected function getObjectData($key)
    {
        $target = app($this->config['class']);
        if (method_exists($target, 'getShareCacheData')) {
            $data = $target->getShareCacheData($key);
            if ($data === null || $data === false) {
                return null;
            }
            if (is_array($data)) {
                $data = json_encode($data);
            }
            if (is_object($data)) {
                throw new ShareCacheException('model or repository store data type error.');
            }
            return $data;
        }
        if ($target instanceof Model) {
            $data = $target->newQuery()->find($key);
            return $data ? $data->toJson() : null;
        }
        throw new ShareCacheException('model or repository serialization function not define.');
    }

    protected function format($value)
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, JSON_OBJECT_AS_ARRAY);
            } catch (\Exception $exception) {
                $value = null;
            }
        }
        return $value;
    }
}
