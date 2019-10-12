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

    public function getManager()
    {
        return $this->manager;
    }

    public function isRemote()
    {
        return $this->getName() != $this->manager->getConfig('name');
    }

    public function object($name)
    {
        if (!isset($this->config['objects'][$name])) {
            throw new ShareCacheException("Object $name not defined.");
        }
        return new ShareCacheObject($name, $this->config['objects'][$name], $this);
    }

    public function delByModel($model)
    {
        $class = get_class($model);

        $keys = array();

        foreach ($this->config['objects'] as $name => $object) {
            if (($object['type'] === 'model' && $object['class'] === $class) ||
                ($object['type'] === 'repository' && in_array($class, $object['models']))) {
                $keys[] = $this->name . ':' . $name;
            }
        }

        if (count($keys)) {
            $this->manager->getDriver()->del($keys);
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
