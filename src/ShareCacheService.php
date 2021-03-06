<?php

namespace YiluTech\ShareCache;

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

    protected static $objectInstances = [];

    public function __construct($name, array $config, ShareCacheServiceManager $manager)
    {
        $this->name = $name;
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
     * @return ShareCacheObject
     * @throws ShareCacheException
     */
    public function object($name)
    {
        if (empty(static::$objectInstances[$name])) {
            if (empty($object = $this->config['objects'][$name])) {
                throw new ShareCacheException("Share cache object[{$this->name}:$name] undefined.");
            }
            static::$objectInstances[$name] = new ShareCacheObject($name, $object, $this);
        }
        return static::$objectInstances[$name];
    }

    public function delByModel($model)
    {
        $class = get_class($model);
        foreach ($this->config['objects'] as $name => $object) {
            if (($object['type'] === 'model' && $object['class'] === $class) ||
                (isset($object['depends']) && in_array($class, $object['depends']))) {
                $this->object($name)->del($model->getKey());
            }
        }
        return $this;
    }

    public function delByEvent($event, $keys)
    {
        foreach ($this->config['objects'] as $name => $object) {
            if (isset($object['events']) && in_array($event, $object['events'])) {
                $this->object($name)->del(implode('-', $keys));
            }
        }
        return $this;
    }

    public function __call($name, $arguments)
    {
        if (count($arguments)) {
            $object = $this->object(array_shift($arguments));
            return $object->{$name}(...$arguments);
        }
        return $this->object($name);
    }
}
