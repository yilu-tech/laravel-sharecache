<?php


namespace YiluTech\ShareCache;


use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use YiluTech\ShareCache\Contracts\CacheMap;
use YiluTech\ShareCache\Contracts\CacheObject;

class Config
{
    protected $options;

    public function __construct($options = [])
    {
        $this->options = $options;
    }

    public function cacheable()
    {
        return [
            'url'     => $this->get('url'),
            'name'    => $this->get('name'),
            'objects' => $this->getObjects()
        ];
    }

    public function get($key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function getObjects()
    {
        $objects = array();

        $defaultTtl = $this->get('ttl', 86400 * 30);

        if (!empty($models = $this->get('models'))) {
            $objects = $this->parseModels($models, $defaultTtl);
        }

        if (!empty($classes = $this->get('objects'))) {
            $objects = array_merge($objects, $this->parseObjects($classes, $defaultTtl));
        }

        if (!empty($repositories = $this->get('repositories'))) {
            $objects = array_merge($objects, $this->parseRepositories($repositories, $defaultTtl));
        }

        return $objects;
    }

    protected function parseObjects($classes, $defaultTtl)
    {
        $objects = [];
        foreach ($classes as $class) {
            $object = Container::getInstance()->make($class);
            if ($object instanceof CacheMap) {
                $type = 'map';
            } elseif ($object instanceof CacheObject) {
                $type = 'object';
            } else {
                throw new \Exception(sprintf('cache object[%s] not support.', $class));
            }

            $options = ['type' => $type, 'class' => $class, 'classType' => 'interface', 'ttl' => $object->ttl ?? $defaultTtl];
            if (isset($object->events)) {
                $options['events'] = (array)$object->events;
            }
            if (isset($object->depends)) {
                $options['depends'] = (array)$object->depends;
            }
            $objects[$object->name()] = $options;
        }
        return $objects;
    }

    protected function parseModels($models, $defaultTtl)
    {
        return array_map(function ($model) use ($defaultTtl) {
            if (is_array($model)) {
                $ttl   = $model['ttl'] ?? $defaultTtl;
                $model = $model['class'];
            }
            return ['type' => 'map', 'classType' => 'model', 'class' => $model, 'ttl' => $ttl ?? $defaultTtl];
        }, $models);
    }

    protected function parseRepositories($repositories, $defaultTtl)
    {
        $objects = [];
        foreach ($repositories as $name => $repository) {
            if (is_array($repository)) {
                $ttl        = $repository['ttl'] ?? $defaultTtl;
                $repository = $repository['class'];
            }

            $reflection = new \ReflectionClass($repository);

            foreach ($reflection->getMethods() as $method) {
                $metadata = $this->getMethodMetadata($method);

                if (isset($metadata['sharecache'])) {
                    $objectName = is_integer($name) ? $metadata['sharecache'] : $name . '.' . $metadata['sharecache'];
                    $object     = [
                        'type'      => $method->getNumberOfParameters() ? 'map' : 'object',
                        'class'     => $repository . '@' . $method->getName(),
                        'classType' => 'repo',
                        'ttl'       => $metadata['ttl'] ?? $ttl ?? $defaultTtl
                    ];
                    if ($object['type'] === 'map') {
                        $object['keys'] = array_map(function ($parameter) {
                            return $parameter->getName();
                        }, $method->getParameters());
                    }
                    if (!empty($metadata['depends'])) {
                        $object['depends'] = $metadata['depends'];
                    }
                    if (!empty($metadata['events'])) {
                        $object['events'] = $metadata['events'];
                    }
                    $objects[$objectName] = $object;
                }
            }
        }
        return $objects;
    }

    protected function getMethodMetadata($reflectionMethod)
    {
        $metadata = [];
        if (($doc = $reflectionMethod->getDocComment()) &&
            preg_match_all('/(?:@([\w\\\]+))(?:[ ]+(.+)\s*)?/', $doc, $matches)) {

            foreach ($matches[1] as $index => $name) {
                switch ($name) {
                    case 'depends':
                        $segments = explode('::', $matches[2][$index]);
                        if (is_subclass_of($segments[0], Model::class)) {
                            $metadata[$name][ltrim($segments[0], '\\')] = $segments[1] ?? 'getKey()';
                        }
                        break;
                    case 'events':
                        if (class_exists($class = $matches[2][$index])) {
                            $metadata[$name][] = $class;
                        }
                        break;
                    case 'param':
                        $parts = preg_split('/[\s|]+/', $matches[2][$index]);

                        $metadata[$name][array_pop($parts)] = $parts;
                        break;
                    case 'return':
                        $metadata[$name] = preg_split('/[\s|]+/', $matches[2][$index]);
                        break;
                    case 'sharecache':
                        $metadata[$name] = $matches[2][$index] ?: $reflectionMethod->getName();
                        break;
                    case 'ttl':
                        $metadata[$name] = intval($matches[2][$index]);
                        break;
                    default:
                        $metadata[$name][] = $matches[2][$index] ?: null;
                        break;
                }
            }
        }
        return $metadata;
    }
}
