<?php
namespace TsaiYiHua\Cache;

use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/pagecache.php' => config_path('pagecache.php'),
        ], 'pagecache');

        /*-----------------------------------------------------------------------
        | Register Console Commands
        |-----------------------------------------------------------------------*/
        if ($this->app->runningInConsole()) {
            $this->commands([
                \TsaiYiHua\Cache\Commands\CacheCommand::class,
                \TsaiYiHua\Cache\Commands\CacheRefreshCommand::class,
                \TsaiYiHua\Cache\Commands\CacheInfoCommand::class
            ]);
        }
    }
}