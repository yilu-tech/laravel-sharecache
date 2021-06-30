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
        $this->name    = $name;
        $this->config  = $config;
        $this->service = $service;

        $this->store = $service->getManager()->getStore();
    }

    public function getName()
    {
        return $this->service->getName() . ':' . $this->name;
    }

    public function getStore()
    {
        return $this->store;
    }

    public function key(): string
    {
        return $this->getName() . ':data';
    }

    public function get()
    {
        $value = $this->store->get($this->key());
        if ($value === null) {
            $value = $this->restore();
        }
        return $value;
    }

    public function set($value)
    {
        $this->store->put($this->key(), $value, $this->config['ttl']);
    }

    public function has()
    {
        return $this->store->has($this->key());
    }

    public function del()
    {
        return $this->store->forget($this->key());
    }

    public function flush()
    {
        return $this->del();
    }

    public function count()
    {
        return $this->has() ? 1 : 0;
    }

    public function restore()
    {
        return $this->service->isRemote()
            ? $this->remoteRestore()
            : $this->localRestore();
    }

    protected function localRestore()
    {
        $value = $this->getOriginal();

        if ($value === null) {
            throw new ShareCacheException(sprintf('Share cache object[%s] value can not be null', $this->getName()));
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } else if (is_object($value)) {
            $value = (array)$value;
        }

        $this->set($value);

        return $value;
    }

    protected function remoteRestore()
    {
        try {
            $uri     = $this->service->getUrl() . '/sharecache/restore';
            $content = (new Client())->post($uri, [
                'header' => ['Accept' => 'application/json'],
                'json'   => ['name' => $this->name],
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
     * @return false|string|null
     * @throws ShareCacheException
     */
    protected function getOriginal()
    {
        switch ($this->config['type']) {
            case 'object':
                return app($this->config['class'])->get();
            case 'repo.object':
                return app()->call($this->config['class']);
            default:
                throw new ShareCacheException(sprintf('Invalid object[%s] type.', $this->getName()));
        }
    }
}
