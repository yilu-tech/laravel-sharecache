<?php

namespace YiluTech\ShareCache;

use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Store;
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

    /**
     * @var Store
     */
    protected $store;

    public function __construct($name, array $config, ShareCacheService $service)
    {
        $this->name = $name;
        $this->config = $config;
        $this->service = $service;
        $this->store = $service->getManager()->getStore()->tags($service->getName() . ':' . $name);
    }

    public function getName()
    {
        return $this->service->getName() . ':' . $this->name;
    }

    public function getStore()
    {
        return $this->store;
    }

    public function get($key)
    {
        if (is_array($key)) {
            return $this->getMany($key);
        }

        if ($key === null || $key === '') {
            return null;
        }

        $value = $this->store->get($key);

        if ($value === null) {
            $value = $this->put($key);
        }
        return $value;
    }

    public function getMany($keys)
    {
        $keys = array_filter($keys, function ($key) {
            return $key !== null && $key !== '';
        });

        if (empty($keys)) {
            return $keys;
        }

        $values = $this->store->many(array_unique($keys));

        foreach ($values as $key => $value) {
            if ($value === null) {
                $values[$key] = $this->put($key);
            }
        }
        return $values;
    }

    public function set($key, $value = null)
    {
        $ttl = $this->config['ttl'] ?? 30 * 86400;
        if (is_array($key)) {
            $this->store->putMany($key, $ttl);
        } else {
            $this->store->put($key, $value, $ttl);
        }
    }

    public function has($key)
    {
        return $this->store->get($key);
    }

    public function del($key)
    {
        return $this->store->forget($key);
    }

    public function flush()
    {
        return $this->store->flush();
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
            $this->set($key, $data);
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
            if (is_object($data)) {
                throw new ShareCacheException('model or repository store data type error.');
            }
            return $data;
        }
        if ($target instanceof Model) {
            $data = $target->newQuery()->find($key);
            return $data ? $data->toArray() : null;
        }
        throw new ShareCacheException('model or repository serialization function not define.');
    }
}
