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

class FlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shareCache:flush {server?*} {--model=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'flush share cache server info';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->flushServers();
        $this->showServers();
    }

    protected function flushServers()
    {
        $server_keys = $this->argument('server');
        $cache_key = $this->getCachePrefix() . 's';

        $servers = ShareCacheManager::getServers();
        $empty = !count($server_keys);
        foreach ($servers as $name => $server) {
            if ($empty || array_search($name, $server_keys) !== false) {
                $this->flushServer($name, $server);
                unset($servers[$name]);
            }
        }
        if (count($servers)) {
            Redis::set($cache_key, json_encode($servers));
        } else {
            Redis::del($cache_key);
        }
    }

    protected function flushServer($name, $server)
    {
        $model_keys = $this->option('model');

        $empty = !count($model_keys);

        $prefix = $this->getCachePrefix();

        foreach ($server['models'] as $key => $model) {
            if ($empty || array_search($key, $model_keys)) {
                Redis::del("$prefix:$name:model:$key");
                $this->info("server:[$name] model:$key flushed.");
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
