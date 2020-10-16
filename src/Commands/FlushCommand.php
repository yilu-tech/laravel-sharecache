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
        $object_keys = $this->option('object');

        $empty = empty($server_keys);
        foreach ($manager->getServers() as $name => $server) {
            if ($empty || in_array($name, $server_keys)) {
                $this->flushServer($manager->service($name), $object_keys);
            }
        }
    }

    protected function flushServer($service, $objects)
    {
        $empty = !count($objects);
        foreach ($service->getObjects() as $key => $object) {
            if ($empty || in_array($key, $objects)) {
                $service->object($key)->flush();
                $this->info(sprintf('server:[%s] %s:%s flushed.', $service->getName(), $object['type'], $key));
            }
        }
    }
}
