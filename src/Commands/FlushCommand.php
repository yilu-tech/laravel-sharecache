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

class FlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharecache:flush {--server=*} {--object=*}';

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

        $this->call('sharecache:list');
    }

    protected function flushServers()
    {
        $manager = app(ShareCacheServiceManager::class);

        $server_keys = $this->option('server');

        $servers = $manager->getServers();

        $empty = !count($server_keys);
        foreach ($servers as $name => $server) {
            if ($empty || in_array($name, $server_keys)) {
                $this->flushServer($manager, $servers, $name);
            }
        }
        $manager->setServers($servers);
    }

    /**
     * @param ShareCacheServiceManager $manager
     * @param $servers
     * @param $name
     */
    protected function flushServer($manager, &$servers, $name)
    {
        $object_keys = $this->option('object');
        $empty = !count($object_keys);

        foreach ($servers[$name]['objects'] as $key => $object) {
            if ($empty || in_array($key, $object_keys)) {
                $manager->getDriver()->del([$manager->applyPrefix("$name:$key")]);
                $this->info("server:[$name] {$object['type']}:$key flushed.");
                unset($servers[$name]['objects'][$key]);
            }
        }

        if ($empty) {
            unset($servers[$name]);
        }
    }
}
