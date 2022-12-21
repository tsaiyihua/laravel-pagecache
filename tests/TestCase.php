<?php
namespace TsaiYiHua\Cache\Tests;

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
