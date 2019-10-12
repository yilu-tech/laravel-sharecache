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

class ShowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharecache:list {--server=} {--object=} {--except}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'show share cache server info';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $manager = app(ShareCacheServiceManager::class);

        $except = $this->option('except');

        $servers = collect($manager->getServers());

        if ($preg = $this->option('server')) {
            $preg = "/$preg/";
            $servers = $servers->filter(function ($server, $name) use ($preg, $except) {
                return !(!$except ^ (bool)preg_match($preg, $name));
            });
        }

        if ($preg = $this->option('object')) {
            $preg = "/$preg/";
        }

        $objects = $servers->flatMap(function ($server, $server_name) use ($preg, $except, $manager) {
            $objects = collect($server['objects']);
            if ($preg) {
                $objects = $objects->filter(function ($object, $name) use ($preg, $except) {
                    return !($except ^ (bool)preg_match($preg, $name));
                });
            }
            return $objects->map(function ($object, $name) use ($server, $server_name, $manager) {
                return [$server_name, $server['url'], "$name => {$object['class']}", $manager->getDriver()->hlen("$server_name:$name")];
            });
        });

        $this->table(['server', 'url', 'object', 'count'], $objects);
    }
}
