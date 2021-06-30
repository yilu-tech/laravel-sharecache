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
        /** @var ShareCacheServiceManager $manager */
        $manager = app(ShareCacheServiceManager::class);
        $config = $manager->getConfig()->cacheable();

        if (empty($name = $config['name']) || empty($config['url'])) {
            $this->error('name or url not define.');
            return false;
        }
        if (empty($config['objects'])) {
            $this->error('not define object.');
            return false;
        }

        $servers = $manager->getServers();

        if (isset($servers[$name])) {
            $diff = array_udiff_assoc($servers[$name]['objects'], $config['objects'], function ($a, $b) {
                return $a <=> $b;
            });
            $this->removeObjects($manager->service($name), $diff);
        }

        $servers[$name] = $config;
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
}
