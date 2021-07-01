<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 11:57
 */

namespace YiluTech\ShareCache;

use Illuminate\Support\Facades\Event;
use YiluTech\ShareCache\Commands\FlushCommand;
use YiluTech\ShareCache\Commands\RegisterCommand;
use YiluTech\ShareCache\Commands\ShowCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShareCacheServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ShareCacheServiceManager::class, function ($app) {
            $config = new Config(config('sharecache', []));
            return new ShareCacheServiceManager($config);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterCommand::class,
                FlushCommand::class,
                ShowCommand::class
            ]);
        }

        $this->registerRoute();

        $this->offerPublishing();
    }

    public function boot()
    {
        $this->registerFlushEvent();
    }

    protected function registerFlushEvent()
    {
        if (empty($config = $this->getCacheConfig())) {
            return;
        }

        [$models, $events] = $this->getCacheFlushEvents($config);

        $listener = function ($model) {
            app(ShareCacheServiceManager::class)->service()->delByModel($model);
        };
        foreach ($models as $model) {
            $model::created($listener);
            $model::updated($listener);
            $model::deleted($listener);
        }

        foreach ($events as $event) {
            Event::listen($event, function () use ($event) {
                app(ShareCacheServiceManager::class)->service()->delByEvent($event, func_get_args());
            });
        }
    }

    protected function getCacheConfig()
    {
        $path = $this->app->bootstrapPath('cache/sharecache.php');

        if (file_exists($path)) {
            return require $path;
        }

        return tap(app(ShareCacheServiceManager::class)->getConfig()->cacheable(), function ($config) use ($path) {
            app(\Illuminate\Filesystem\Filesystem::class)->put(
                $path, '<?php return ' . var_export($config, true) . ';' . PHP_EOL
            );
        });
    }

    protected function getCacheFlushEvents($config)
    {
        $models = [];
        $events = [];
        foreach ($config['objects'] as $key => $object) {
            if ($object['classType'] === 'model') {
                $models[] = $object['class'];
            } else {
                if (!empty($object['depends'])) {
                    $models = array_merge($models, array_keys($object['depends']));
                }
                if (!empty($object['events'])) {
                    $events = array_merge($events, $object['events']);
                }
            }
        }
        return [array_unique($models), array_unique($events)];
    }

    protected function registerRoute()
    {
        $defaultOptions = [
            'namespace' => '\YiluTech\ShareCache',
        ];

        $options = array_merge($defaultOptions, $this->app['config']['sharecache']['route_option'] ?? []);
        Route::group($options, function ($router) {
            Route::post('sharecache/restore', 'ShareCacheController@restore')->name('sharecache.restore');
        });
    }

    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sharecache.php' => config_path('sharecache.php'),
            ], 'sharecache-config');
        }
    }
}
