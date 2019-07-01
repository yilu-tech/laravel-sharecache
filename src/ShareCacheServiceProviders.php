<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/27
 * Time: 11:57
 */

namespace YiluTech\ShareCache;

use YiluTech\ShareCache\Commands\FlushCommand;
use YiluTech\ShareCache\Commands\RegisterCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShareCacheServiceProviders extends ServiceProvider
{
    public function boot()
    {
        $this->registerModelEvent();
    }

    public function register()
    {
        $this->app->singleton(ShareCacheManager::class, function ($app) {
            return new ShareCacheManager($app);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterCommand::class,
                FlushCommand::class
            ]);
        }

        $this->registerRoute();

        $this->offerPublishing();
    }

    protected function registerModelEvent()
    {
        $models = Util::array_get($this->app['config']['sharecache'], 'models', []);
        foreach ($models as $model) {
            $model::created([ModelEventListener::class, 'created']);
            $model::updated([ModelEventListener::class, 'updated']);
            $model::deleted([ModelEventListener::class, 'deleted']);
            $model::saved([ModelEventListener::class, 'saved']);
        }
    }

    protected function registerRoute()
    {
        $defaultOptions = [
            'namespace' => '\YiluTech\ShareCache',
        ];
        $options = array_merge($defaultOptions, $this->app['config']['sharecache']['route_option'] ?? []);

        Route::group($options, function ($router) {
            Route::post('sharecache/sync', 'ShareCacheController@sync')->name('sharecache.sync');
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
