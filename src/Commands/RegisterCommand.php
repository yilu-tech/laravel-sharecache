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
    protected $signature = 'shareCache:register';

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
        $servers = ShareCacheManager::getServers();

        $config = ShareCacheManager::getConfig();

        $save_keys = ['models', 'url'];

        $servers[$config['server']] = array_intersect_key($config, array_flip($save_keys));

        $cache_key = ShareCacheManager::getConfig('cache_prefix', 'sharecache') . ':servers';

        Redis::set($cache_key, json_encode($servers));

        $this->info('register success.');
    }

    protected function showServers()
    {
        $servers = ShareCacheManager::getServers();

        $prefix = ShareCacheManager::getConfig('cache_prefix', 'sharecache') . ':server';

        $this->table(
            ['server', 'url', 'model', 'count'],
            collect($servers)->flatMap(function ($server, $server_name) use ($prefix) {
                return collect($server['models'])->map(function ($model, $name) use ($server, $server_name, $prefix) {
                    return [$server_name, $server['url'], "$name => $model", Redis::hlen("$prefix:$server_name:model:$name")];
                });
            })
        );
    }
}
