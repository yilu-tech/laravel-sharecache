<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 14:05
 */

namespace YiluTech\ShareCache\Commands;

use YiluTech\ShareCache\ShareCacheServiceManager;
use Illuminate\Console\Command;
use YiluTech\ShareCache\Util;

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

        if (isset($objects[$name])) {
            $this->error('model or repository name can not define server name.');
            return false;
        }

        $servers = $manager->getServers();

        if (isset($servers[$name])) {
            $this->removeObjects($manager->service($name), array_diff_key($servers[$name]['objects'] ?? [], $objects));
        }

        $servers[$name] = compact('url', 'objects');

        $manager->setServers($servers);

        $this->info('register success.');
    }

    protected function removeObjects($server, $objects)
    {
        foreach ($objects as $name => $object) {
            $server->object($name)->flush();
            $this->info("remove model $name.");
        }
    }

    public function getObjects($config)
    {
        $objects = array();
        $defaultTtl = $config['ttl'] ?? 86400 * 30;
        foreach ($config['models'] ?? [] as $name => $model) {
            if (is_array($model)) {
                $model = $model['class'];
                $ttl = $model['ttl'] ?? $defaultTtl;
            }
            $objects[$name] = ['type' => 'model', 'class' => $model, 'ttl' => $ttl ?? $defaultTtl];
        }
        foreach ($config['repositories'] ?? [] as $name => $repository) {
            if (is_array($repository)) {
                $repository = $repository['class'];
                $ttl = $repository['ttl'] ?? $defaultTtl;
            }
            $objects[$name] = ['type' => 'repository', 'class' => $repository, 'models' => Util::getRepositoryProviders($repository), 'ttl' => $ttl ?? $defaultTtl];
        }
        return $objects;
    }
}
