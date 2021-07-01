<?php

namespace YiluTech\ShareCache;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Support\Arrayable;


class ShareCacheMap
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
        $this->name    = $name;
        $this->config  = $config;
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

    public function get($key)
    {
        if (is_array($key)) {
            return $this->getMany($key);
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

        if (!empty($empties = array_filter($values, 'is_null'))) {
            $values = array_replace($values, $this->restoreMany(array_keys($empties)));
        }
        return $values;
    }

    public function set($key, $value = null)
    {
        if (is_array($key)) {
            $this->store->putMany($key, $this->config['ttl']);
        } else {
            $this->store->put($key, $value, $this->config['ttl']);
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

    public function getIterator()
    {


    }

    public function restore($key)
    {
        if ($this->service->isRemote()) {
            return $this->remoteRestore($key);
        }
        return $this->setOriginalValue($key, $this->getOriginal($key));
    }

    public function restoreMany($keys)
    {
        if ($this->service->isRemote()) {
            return $this->remoteRestore($keys);
        }

        $values = $this->getOriginal($keys);
        foreach ($keys as $key) {
            $values[$key] = $this->setOriginalValue($key, $values[$key] ?? null);
        }

        return $values;
    }

    protected function setOriginalValue($key, $value)
    {
        if ($value === null) {
            throw new ShareCacheException(sprintf('Share cache object[%s:%s] value can not be null', $this->getName(), $key));
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } else if (is_object($value)) {
            $value = (array)$value;
        }

        $this->set($key, $value);

        return $value;
    }

    protected function remoteRestore($keys)
    {
        try {
            $uri     = $this->service->getUrl() . '/sharecache/restore';
            $content = (new Client())->get($uri, [
                'header' => ['Accept' => 'application/json'],
                'json'   => [
                    'name' => $this->name,
                    'keys' => $keys
                ],
            ])->getBody()->getContents();
            return json_decode($content, true);
        } catch (RequestException $exception) {
            if ($exception->getCode() === 406) {
                $result = json_decode($exception->getResponse()->getBody()->getContents(), true);
                throw new ShareCacheException($result['message'], 0, $exception);
            }
            throw $exception;
        }
    }

    /**
     * @param $key
     * @return array|false|string|null
     * @throws ShareCacheException
     */
    protected function getOriginal($key)
    {
        switch ($this->config['classType']) {
            case 'model':
                $model = app($this->config['class']);
                return is_array($key) ? $model->findMany($key)->keyBy($model->getKeyName())->all() : $model->find($key);
            case 'interface':
                $instance = app($this->config['class']);
                return is_array($key) ? $instance->getMany($key) : $instance->get($key);
            case 'repo':
                if (is_array($key)) {
                    return array_combine($key, array_map([$this, 'callRepositoryObject'], $key));
                }
                return $this->callRepositoryObject($key);
            default:
                throw new ShareCacheException(sprintf('Invalid object[%s] type.', $this->getName()));
        }
    }

    protected function callRepositoryObject($key)
    {
        $keys = explode('-', $key);
        if (count($this->config['keys']) !== count($keys)) {
            throw new ShareCacheException(sprintf('Invalid object[%s] key[%s], should define as [%s].', $this->getName(), $key, implode('-', $this->config['keys'])));
        }
        return app()->call($this->config['class'], array_combine($this->config['keys'], $keys));
    }
}
