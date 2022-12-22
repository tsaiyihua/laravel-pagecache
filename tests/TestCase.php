<?php
namespace TsaiYiHua\Cache\Tests;

use Illuminate\Support\Str;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @param $app
     * @return string[]
     */
    protected function getPackageProviders($app)
    {
        return [
            \TsaiYiHua\Cache\CacheServiceProvider::class,
        ];
    }

    /**
     * @param $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('pagecache', include __DIR__.'/../config/pagecache.php');
        $app['config']->set('app.debug', true);
        $app['config']->set('filesystems', [
            'default' => env('FILESYSTEM_DISK', 'local'),
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => storage_path('app'),
                ],

                'public' => [
                    'driver' => 'local',
                    'root' => storage_path('app/public'),
                    'url' => env('APP_URL').'/storage',
                    'visibility' => 'public',
                ],
                'pages' => [
                    'driver' => 'local',
                    'root' => env('PAGE_CACHE_DISK', storage_path('app/testing/pages')),
                ]
            ],
            'links' => [
                public_path('storage') => storage_path('app/public'),
            ],
        ]);
        $app['config']->set('database', [
            'redis' => [
                'client' => env('REDIS_CLIENT', 'phpredis'),
                'options' => [
                    'cluster' => env('REDIS_CLUSTER', 'redis'),
                    'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
                ],
                'default' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'password' => env('REDIS_PASSWORD'),
                    'port' => env('REDIS_PORT', '6379'),
                    'database' => env('REDIS_DB', '0'),
                ],
                'cache' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'password' => env('REDIS_PASSWORD'),
                    'port' => env('REDIS_PORT', '6379'),
                    'database' => env('REDIS_CACHE_DB', '1'),
                ],
            ]
        ]);
    }

    /**
     * @param $method
     * @param $content
     * @param $uri
     * @param $server
     * @param $parameters
     * @param $cookies
     * @param $files
     * @return \Illuminate\Http\Request
     */
    public function createRequest(
        $method,
        $content,
        $uri = '/test',
        $server = ['CONTENT_TYPE' => 'application/json'],
        $parameters = [],
        $cookies = [],
        $files = []
    ) {
        $request = new \Illuminate\Http\Request;
        return $request->createFromBase(
            \Symfony\Component\HttpFoundation\Request::create(
                $uri,
                $method,
                $parameters,
                $cookies,
                $files,
                $server,
                $content
            )
        );
    }
}
