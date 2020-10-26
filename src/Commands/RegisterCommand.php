<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 14:05
 */

namespace YiluTech\ShareCache\Commands;

use Illuminate\Database\Eloquent\Model;
use YiluTech\ShareCache\ShareCacheServiceManager;
use Illuminate\Console\Command;

class RegisterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharecache:register';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'register share cache server info';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->setServer();
        $this->call('sharecache:list');
    }

    protected function setServer()
    {
        $manager = app(ShareCacheServiceManager::class);

        $name = $manager->getConfig('name');
        $url = $manager->getConfig('url');

        if (!$name || !$url) {
            $this->error('name or url not define.');
            return false;
        }

        $objects = $this->getObjects($manager->getConfig());

        if (empty($objects)) {
            $this->error('not define object.');
            return false;
        }

        $servers = $manager->getServers();

        if (isset($servers[$name])) {
            $diff = array_udiff_assoc($servers[$name]['objects'], $objects, function ($a, $b) {
                return $a <=> $b;
            });
            $this->removeObjects($manager->service($name), $diff);
        }

        $servers[$name] = compact('url', 'objects');

        $manager->setServers($servers);
        $this->removeBootstrapCache();

        $this->info('register success.');
    }

    protected function removeObjects($server, $objects)
    {
        foreach ($objects as $name => $object) {
            $server->object($name)->flush();
            $this->info("remove model $name.");
        }
    }

    protected function removeBootstrapCache()
    {
        if (file_exists($path = $this->laravel->bootstrapPath('cache/sharecache.php'))) {
            @unlink($path);
        }
    }

    protected function getObjects($config)
    {
        $objects = array();
        $defaultTtl = $config['ttl'] ?? 86400 * 30;
        if (!empty($config['models'])) {
            $objects = $this->parseModels($config['models'], $defaultTtl);
        }
        if (!empty($config['repositories'])) {
            $objects = array_merge($objects, $this->parseRepositories($config['repositories'], $defaultTtl));
        }
        return $objects;
    }

    protected function parseModels($models, $defaultTtl)
    {
        $objects = [];
        foreach ($models as $name => $model) {
            if (is_array($model)) {
                $ttl = $model['ttl'] ?? $defaultTtl;
                $model = $model['class'];
            }
            $objects[$name] = ['type' => 'model', 'class' => $model, 'ttl' => $ttl ?? $defaultTtl];
        }
        return $objects;
    }

    protected function parseRepositories($repositories, $defaultTtl)
    {
        $objects = [];
        foreach ($repositories as $name => $repository) {
            if (is_array($repository)) {
                $ttl = $repository['ttl'] ?? $defaultTtl;
                $repository = $repository['class'];
            }

            $reflection = new \ReflectionClass($repository);

            foreach ($reflection->getMethods() as $method) {
                $metadata = $this->getMethodMetadata($method);

                if (isset($metadata['sharecache'])) {
                    $objectName = is_integer($name) ? $metadata['sharecache'] : $name . '.' . $metadata['sharecache'];
                    $object = [
                        'type' => $method->getNumberOfParameters() ? 'array' : 'object',
                        'class' => $repository . '@' . $method->getName(),
                        'ttl' => $metadata['ttl'] ?? $ttl ?? $defaultTtl
                    ];
                    if ($object['type'] === 'array') {
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
            preg_match_all('/(?:@([\w\\\]+))(?:[ ]+(.+)[ ]*)?/', $doc, $matches)) {

            foreach ($matches[1] as $index => $name) {
                switch ($name) {
                    case 'depends':
                        $class = $matches[2][$index];
                        if (is_subclass_of($class, Model::class)) {
                            $metadata[$name][] = $class;
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
