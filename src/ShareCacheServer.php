<?php

namespace YiluTech\ShareCache;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;

/**
 * Class SharedCache
 *
 */
class ShareCacheServer
{
    protected $name;

    protected $models;

    protected $config;

    public function __construct($config)
    {
        if (empty($config['name'])) {
            throw new ShareCacheException('share cache server name not define.');
        }
        $this->name = $config['name'];
        $this->models = ShareCacheManager::getModels($config);
        $this->config = $config;
    }

    public function getName()
    {
        return $this->name;
    }

    public function get($name, $key)
    {
        $value = Redis::hget($this->getCacheKey($name), $key);

        if ($value === null) {
            $value = $this->put($name, $key);
        }

        if ($value) {
            $value = json_decode($value, JSON_OBJECT_AS_ARRAY);
        }

        return $value;
    }

    public function has($name, $key)
    {
        return Redis::hexists($this->getCacheKey($name), $key);
    }

    public function put($name, $key)
    {
        if ($this->name === ShareCacheManager::getConfig('server')) {
            return $this->callPut($name, $key);
        } else {
            return $this->callRemotePut($name, $key);
        }
    }

    /**
     * @param string
     * @param $model \Illuminate\Database\Eloquent\Model
     * @return $string
     */
    public function setModel($name, $model)
    {
        $key = $model->getKey();

        $data = $this->modelToJson($model);

        Redis::hset($this->getCacheKey($name), $key, $data);

        return $data;
    }

    /**
     * @param string
     * @param $model\Illuminate\Database\Eloquent\Model
     */
    public function delModel($name, $model)
    {
        Redis::hdel($this->getCacheKey($name), $model->getKey());
    }

    protected function callPut($name, $key)
    {
        $model = $this->getModel($name);

        $data = $model::query()->find($key);

        return $data ? $this->setModel($name, $data) : $data;
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

    protected function getCacheKey($model)
    {
        $prefix = Util::array_get($this->config, 'cache_prefix', 'sharecache');
        return "$prefix:server:{$this->name}:model:$model";
    }

    /**
     * @param $name
     * @return \Illuminate\Database\Eloquent\Model
     * @throws ShareCacheException
     */
    protected function getModel($name)
    {
        if (empty($this->models[$name])) {
            throw new ShareCacheException("share cache server:[{$this->name}] model \"$name\" not define.");
        }
        return $this->models[$name];
    }

    /**
     * @param $model\Illuminate\Database\Eloquent\Model
     * @return $string
     */
    protected function modelToJson($model)
    {
        if (method_exists($model, 'getShareCache')) {
            return json_encode($model->getShareCache());
        }
        return $model->toJson();
    }

}
