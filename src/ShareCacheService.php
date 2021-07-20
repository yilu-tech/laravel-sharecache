<?php

namespace YiluTech\ShareCache;

use Illuminate\Database\Events\TransactionCommitted;

/**
 * Class SharedCache
 *
 */
class ShareCacheService
{
    protected $name;

    protected $config;

    /**
     * @var ShareCacheServiceManager
     */
    protected $manager;

    protected $delKeys = [];

    protected static $objectInstances = [];

    public function __construct($name, array $config, ShareCacheServiceManager $manager)
    {
        $this->name   = $name;
        $this->config = $config;

        $this->manager = $manager;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getUrl()
    {
        return $this->config['url'];
    }

    public function getObjects()
    {
        return $this->config['objects'];
    }

    public function getManager()
    {
        return $this->manager;
    }

    public function isRemote()
    {
        return !$this->manager->isCurrentServer($this->getName());
    }

    public function flush()
    {
        foreach ($this->config['objects'] as $key => $object) {
            $this->object($key)->flush();
        }
    }

    /**
     * @param $name
     * @return ShareCacheObject|ShareCacheMap
     * @throws ShareCacheException
     */
    public function object($name)
    {
        if (empty(static::$objectInstances[$name])) {
            $object = $this->config['objects'][$name] ?? null;
            if (empty($object)) {
                throw new ShareCacheException("Share cache object[{$this->name}:$name] undefined.");
            }
            static::$objectInstances[$name]
                = $object['type'] === 'map'
                ? new ShareCacheMap($name, $object, $this)
                : new ShareCacheObject($name, $object, $this);
        }
        return static::$objectInstances[$name];
    }

    public function delByModel($model)
    {
        $class = get_class($model);
        foreach ($this->config['objects'] as $name => $object) {
            if ($object['class'] === $class) {
                $this->removeObjectKey($name, $model->getKey());
            } else if (isset($object['depends'][$class])) {
                $keyName = $object['depends'][$class];
                if (substr_compare($keyName, '()', -2, 2) === 0) {
                    $this->removeObjectKey($name, call_user_func([$model, substr($keyName, 0, -2)]));
                } else {
                    $this->removeObjectKey($name, $model->$keyName);
                }
            }
        }
        return $this;
    }

    public function delByEvent($event, $payload)
    {
        foreach ($this->config['objects'] as $name => $object) {
            if (isset($object['events']) && in_array($event, $object['events'])) {
                if ($object['classType'] === 'interface') {
                    $object = app($object['class']);
                    $object->{$object->events[$event] ?? 'handle'}(...$payload);
                } else {
                    $this->object($name)->del(implode('-', $payload));
                }
            }
        }
        return $this;
    }

    protected function removeObjectKey($object, $key)
    {
        if (\DB::transactionLevel() > 0) {
            if (empty($this->delKeys)) {
                \Event::listen(TransactionCommitted::class, function () {
                    foreach ($this->delKeys as $obj => $keys) {
                        $this->object($obj)->del($keys);
                    }
                    $this->delKeys = [];
                });
            }
            $this->delKeys[$object][] = $key;
        } else {
            $this->object($object)->del($key);
        }
    }

    public function __call($name, $arguments)
    {
        if (count($arguments)) {
            $object = $this->object(array_shift($arguments));
            return $object->{$name}(...$arguments);
        }
        $object = $this->object($name);

        if ($object instanceof ShareCacheObject) {
            return $object->{$name}();
        }
        return $object;
    }
}
