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
        | Register Page Cache Log Channel (daily rotated storage/logs/pagecache.log)
        |-----------------------------------------------------------------------*/
        if (config('logging.channels.pagecache') === null) {
            config(['logging.channels.pagecache' => [
                'driver' => 'daily',
                'path' => storage_path('logs/pagecache.log'),
                'level' => 'info',
                'days' => (int) config('pagecache.logDays', 7),
            ]]);
        }

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