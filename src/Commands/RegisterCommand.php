<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 14:05
 */

namespace YiluTech\ShareCache\Commands;

use YiluTech\ShareCache\ShareCacheManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;


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

        $this->showServers();
    }

    protected function setServer()
    {
        $name = ShareCacheManager::getConfig('name');
        $url = ShareCacheManager::getConfig('url');

        if (!$name || !$url) return;

        $models = ShareCacheManager::getModels();

        $servers = ShareCacheManager::getServers();

        $server = compact('url', 'models');
        if (isset($servers[$name])) {
            $this->removeModels($name, array_diff_key($servers[$name]['models'], $server['models']));
        }
        $servers[$name] = $server;

        $cache_key = $this->getCachePrefix() . 's';
        Redis::set($cache_key, json_encode($servers));
        $this->info('register success.');
    }

    protected function removeModels($name, $models)
    {
        $prefix = $this->getCachePrefix() . ":$name:model:";
        foreach ($models as $name => $model) {
            $key = $prefix . $name;
            if (Redis::exists($key)) {
                Redis::del($key);
                $this->info("remove model $name.");
            }
        }
    }

    protected function showServers()
    {
        $servers = ShareCacheManager::getServers();
        $prefix = $this->getCachePrefix();

        $this->table(
            ['server', 'url', 'model', 'count'],
            collect($servers)->flatMap(function ($server, $server_name) use ($prefix) {
                return collect($server['models'])->map(function ($model, $name) use ($server, $server_name, $prefix) {
                    return [$server_name, $server['url'], "$name => $model", Redis::hlen("$prefix:$server_name:model:$name")];
                });
            })
        );
    }

    protected function getCachePrefix()
    {
        return ShareCacheManager::getConfig('cache_prefix', 'sharecache') . ':server';
    }
}
