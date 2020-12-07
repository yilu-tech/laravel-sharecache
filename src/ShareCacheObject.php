<?php

namespace YiluTech\ShareCache;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Support\Arrayable;


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

        $this->store = $service->getManager()->getStore()->tags($this->getName());
    }

    public function getName()
    {
        return $this->service->getName() . ':' . $this->name;
    }

    public function getStore()
    {
        return $this->store;
    }

    public function get($key = null)
    {
        if (is_array($key)) {
            return $this->getMany($key);
        }

        if ($key === null) {
            $key = 'data';
        }

        $value = $this->store->get($key);

        if ($value === null) {
            $value = $this->restore($key);
        }
        return $value;
    }

    public function getMany($keys)
    {
        if (empty($keys)) {
            return $keys;
        }

        $values = $this->store->many($keys);

        foreach ($values as $key => $value) {
            if ($value === null) {
                $values[$key] = $this->restore($key);
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
        return $this->store->has($key);
    }

    public function del($key)
    {
        return $this->store->forget($key);
    }

    public function flush()
    {
        return $this->store->flush();
    }

    public function count()
    {
        return $this->store->count();
    }

    public function restore($key)
    {
        return $this->service->isRemote()
            ? $this->remoteRestore($key)
            : $this->localRestore($key);
    }

    protected function localRestore($key)
    {
        $value = $this->getOriginal($key);

        if ($value === null) {
            throw new ShareCacheException(sprintf('Share cache [%s:%s] value can not be null', $this->getName(), $key));
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } else if (is_object($value)) {
            $value = (array)$value;
        }

        $this->set($key, $value);

        return $value;
    }

    protected function remoteRestore($key)
    {
        try {
            $uri = $this->service->getUrl() . '/sharecache/restore';
            $content = (new Client())->get($uri, [
                'query' => [
                    'name' => $this->name,
                    'key' => $key
                ],
                'header' => ['Accept' => 'application/json']
            ])->getBody()->getContents();
            return json_decode($content, JSON_OBJECT_AS_ARRAY);
        } catch (RequestException $exception) {
            if ($exception->getCode() === 501) {
                $result = json_decode($exception->getResponse()->getBody()->getContents(), JSON_OBJECT_AS_ARRAY);
                throw new ShareCacheException($result['message'], 0, $exception);
            }
            throw $exception;
        }
    }

    /**
     * @param $key
     * @return false|string|null
     * @throws ShareCacheException
     */
    protected function getOriginal($key)
    {
        switch ($this->config['type']) {
            case 'model':
                return resolve($this->config['class'])->newQuery()->find($key);
            case 'array':
                $keys = explode('-', $key);
                if (count($this->config['keys']) !== count($keys)) {
                    throw new ShareCacheException(sprintf('Invalid object[%s] key[%s], should define as [%s].', $this->getName(), $key, implode('-', $this->config['keys'])));
                }
                return app()->call($this->config['class'], array_combine($this->config['keys'], $keys));
            case 'object':
                return app()->call($this->config['class']);
            default:
                throw new ShareCacheException(sprintf('Invalid object[%s] type.', $this->getName()));
        }
    }
}
