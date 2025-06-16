<?php

namespace TsaiYiHua\Cache\Tests;

use Illuminate\Support\Facades\Artisan;
use TsaiYiHua\Cache\CacheServiceProvider;
use TsaiYiHua\Cache\Commands\CacheCommand;
use TsaiYiHua\Cache\Commands\CacheInfoCommand;
use TsaiYiHua\Cache\Commands\CacheRefreshCommand;

class CacheServiceProviderTest extends TestCase
{
    public function test_config_is_published()
    {
        $this->artisan('vendor:publish', ['--tag' => 'pagecache', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists(config_path('pagecache.php'));
    }

    public function test_commands_are_registered_when_in_console()
    {
        // Ensure the service provider's boot method is called
        $this->app->register(CacheServiceProvider::class);

        // Simulate running in console
        $this->app['Illuminate\\Contracts\\Console\\Kernel']->bootstrap();

        $registeredCommands = Artisan::all();

        $this->assertArrayHasKey('pagecache:clear', $registeredCommands);
        $this->assertArrayHasKey('pagecache:info', $registeredCommands);
        $this->assertArrayHasKey('pagecache:refresh', $registeredCommands);

        $this->assertInstanceOf(CacheCommand::class, $registeredCommands['pagecache:clear']);
        $this->assertInstanceOf(CacheInfoCommand::class, $registeredCommands['pagecache:info']);
        $this->assertInstanceOf(CacheRefreshCommand::class, $registeredCommands['pagecache:refresh']);
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists(config_path('pagecache.php'))) {
            unlink(config_path('pagecache.php'));
        }
        parent::tearDown();
    }
}
