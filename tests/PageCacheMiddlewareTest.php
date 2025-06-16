<?php

namespace TsaiYiHua\Cache\Tests;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use TsaiYiHua\Cache\Http\Middleware\PageCache;
use TsaiYiHua\Cache\Jobs\RenderPage;
use TsaiYiHua\Cache\Services\CacheService;

uses(TestCase::class);

beforeEach(function () {
    /** @var TestCase $this */
    // Default config values that can be overridden per test
    Config::set('app.env', 'production');
    Config::set('pagecache.enable', true);
    Config::set('pagecache.delay', 0); // Default delay for jobs

    $this->cacheServiceMock = $this->mock(CacheService::class);
    // Expect the constructor call to setCacheTime()
    $this->cacheServiceMock->shouldReceive('setCacheTime')->withNoArgs()->once()->andReturnSelf();

    $this->middleware = new PageCache($this->cacheServiceMock);
    Bus::fake(); // Fake the bus for job dispatching checks
});

afterEach(function () {
    \Mockery::close();
});

// Helper to create a request
function createRequest($method = 'GET', $uri = '/', $queryParams = []) {
    $request = Request::create($uri, $method, $queryParams);
    // Simulate route binding if necessary, though not directly needed for these middleware tests
    // $request->setRouteResolver(function () use ($request) {
    //     return (new \Illuminate\Routing\Route($request->method(), $request->path(), function() { return new Response(); }))->bind($request);
    // });
    return $request;
}

// Closure for the $next parameter in middleware
$nextClosure = function ($request) {
    return new Response('Next middleware content for ' . $request->fullUrl(), 200);
};

test('middleware bypasses cache if app.env is not production and nocache=1', function () use (&$nextClosure) {
    /** @var TestCase $this */
    Config::set('app.env', 'local');
    $request = createRequest('GET', '/test', ['nocache' => '1']);

    // cacheServiceMock expectations were set in beforeEach for the constructor call.
    // No further interaction with cacheServiceMock is expected here.

    $response = $this->middleware->handle($request, $nextClosure, 0, 'html');

    $this->assertEquals('Next middleware content for http://localhost/test?nocache=1', $response->getContent());
    Bus::assertNotDispatched(RenderPage::class);
});

test('middleware bypasses cache if pagecache.enable is false', function () use (&$nextClosure) {
    /** @var TestCase $this */
    Config::set('pagecache.enable', false);
    $request = createRequest();

    // No further interaction with cacheServiceMock expected beyond constructor.

    $response = $this->middleware->handle($request, $nextClosure, 0, 'html');

    $this->assertEquals('Next middleware content for http://localhost', $response->getContent());
    Bus::assertNotDispatched(RenderPage::class);
});

test('middleware sets custom cache time if provided', function () use (&$nextClosure) {
    /** @var TestCase $this */
    $request = createRequest();
    $customCacheTime = 3600;

    // Expectation for constructor call already in beforeEach
    $this->cacheServiceMock->shouldReceive('setCacheTime')->with($customCacheTime)->once()->andReturnSelf(); // Called from handle
    $this->cacheServiceMock->shouldReceive('setContentType')->with('html')->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('requestCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('setUrl')->with($request)->twice()->andReturnSelf(); // Once for read, once for job dispatch
    $this->cacheServiceMock->shouldReceive('read')->once()->andReturn(['content' => '', 'update' => 0]); // Cache miss
    $this->cacheServiceMock->shouldReceive('getCacheTime')->once()->andReturn($customCacheTime > 0 ? $customCacheTime : config('pagecache.cache_time', 86400));


    $this->middleware->handle($request, $nextClosure, $customCacheTime, 'html');
    Bus::assertDispatched(RenderPage::class); // Cache miss dispatches RenderPage
});

test('middleware returns json response for json content type with cached content', function () use (&$nextClosure) {
    /** @var TestCase $this */
    $request = createRequest();
    $cachedJson = json_encode(['data' => 'test']);
    $now = time();

    // Expectation for constructor call to setCacheTime() already in beforeEach. No other calls if $cacheTime param is 0.
    $this->cacheServiceMock->shouldReceive('setContentType')->with('json')->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('requestCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('setUrl')->with($request)->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('read')->once()->andReturn(['content' => $cachedJson, 'update' => $now]);
    $this->cacheServiceMock->shouldReceive('getCacheTime')->once()->andReturn(86400); // Not expired
    $this->cacheServiceMock->shouldReceive('hitCount')->once()->andReturn(true);

    $response = $this->middleware->handle($request, $nextClosure, 0, 'json');

    $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
    $this->assertEquals(['data' => 'test'], $response->getData(true));
    Bus::assertNotDispatched(RenderPage::class); // Not expired, no refresh
});

test('middleware handles cache miss correctly', function () use (&$nextClosure) {
    /** @var TestCase $this */
    $request = createRequest();

    // Expectation for constructor call to setCacheTime() already in beforeEach.
    $this->cacheServiceMock->shouldReceive('setContentType')->with('html')->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('requestCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('setUrl')->with($request)->twice()->andReturnSelf(); // Once for read, once for job dispatch
    $this->cacheServiceMock->shouldReceive('read')->once()->andReturn(['content' => '', 'update' => 0]); // Cache miss
    $this->cacheServiceMock->shouldReceive('getCacheTime')->once()->andReturn(config('pagecache.cache_time', 86400));

    $response = $this->middleware->handle($request, $nextClosure, 0, 'html');

    $this->assertEquals('Next middleware content for http://localhost', $response->getContent());
    Bus::assertDispatched(RenderPage::class);
});

test('middleware handles cache hit but expired', function () use (&$nextClosure) {
    /** @var TestCase $this */
    $request = createRequest();
    $cachedContent = 'Old cached content';
    $cacheUpdateTime = time() - 90000; // Expired (default cache time is 86400)
    $defaultCacheTime = 86400;

    // Expectation for constructor call to setCacheTime() already in beforeEach.
    $this->cacheServiceMock->shouldReceive('setContentType')->with('html')->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('requestCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('setUrl')->with($request)->twice()->andReturnSelf(); // Called twice: once for read, once for job dispatch
    $this->cacheServiceMock->shouldReceive('read')->once()->andReturn(['content' => $cachedContent, 'update' => $cacheUpdateTime]);
    $this->cacheServiceMock->shouldReceive('getCacheTime')->once()->andReturn($defaultCacheTime); // This is the existing one for the check
    $this->cacheServiceMock->shouldReceive('hitCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('refreshCount')->once()->andReturn(true);

    $response = $this->middleware->handle($request, $nextClosure, 0, 'html');

    $this->assertEquals($cachedContent, $response->getContent());
    Bus::assertDispatched(RenderPage::class);
});

test('middleware handles cache hit with refresh query param', function () use (&$nextClosure) {
    /** @var TestCase $this */
    $request = createRequest('GET', '/test', ['refresh' => '1']);
    $cachedContent = 'Current cached content';
    $cacheUpdateTime = time() - 1000; // Not expired
    $defaultCacheTime = 86400;

    // Expectation for constructor call to setCacheTime() already in beforeEach.
    $this->cacheServiceMock->shouldReceive('setContentType')->with('html')->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('requestCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('setUrl')->with($request)->twice()->andReturnSelf(); // Called twice: once for read, once for job dispatch
    $this->cacheServiceMock->shouldReceive('read')->once()->andReturn(['content' => $cachedContent, 'update' => $cacheUpdateTime]);
    $this->cacheServiceMock->shouldReceive('getCacheTime')->once()->andReturn($defaultCacheTime); // This is the existing one for the check
    $this->cacheServiceMock->shouldReceive('hitCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('refreshCount')->once()->andReturn(true);

    $response = $this->middleware->handle($request, $nextClosure, 0, 'html');

    $this->assertEquals($cachedContent, $response->getContent());
    Bus::assertDispatched(RenderPage::class);
});

test('middleware handles cache hit not expired no refresh', function () use (&$nextClosure) {
    /** @var TestCase $this */
    $request = createRequest();
    $cachedContent = 'Fresh cached content';
    $cacheUpdateTime = time() - 1000; // Not expired
    $defaultCacheTime = 86400;

    // Expectation for constructor call to setCacheTime() already in beforeEach.
    $this->cacheServiceMock->shouldReceive('setContentType')->with('html')->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('requestCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('setUrl')->with($request)->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('read')->once()->andReturn(['content' => $cachedContent, 'update' => $cacheUpdateTime]);
    $this->cacheServiceMock->shouldReceive('getCacheTime')->once()->andReturn($defaultCacheTime);
    $this->cacheServiceMock->shouldReceive('hitCount')->once()->andReturn(true);
    // refreshCount should NOT be called

    $response = $this->middleware->handle($request, $nextClosure, 0, 'html');

    $this->assertEquals($cachedContent, $response->getContent());
    Bus::assertNotDispatched(RenderPage::class);
});

test('renderpage job is dispatched with delay', function () use (&$nextClosure) {
    /** @var TestCase $this */
    Config::set('pagecache.delay', 10); // 10 seconds delay
    $request = createRequest();

    // Expectation for constructor call to setCacheTime() already in beforeEach.
    $this->cacheServiceMock->shouldReceive('setContentType')->with('html')->once()->andReturnSelf();
    $this->cacheServiceMock->shouldReceive('requestCount')->once()->andReturn(true);
    $this->cacheServiceMock->shouldReceive('setUrl')->with($request)->twice()->andReturnSelf(); // Once for read, once for job dispatch
    $this->cacheServiceMock->shouldReceive('read')->once()->andReturn(['content' => '', 'update' => 0]); // Cache miss
    $this->cacheServiceMock->shouldReceive('getCacheTime')->once()->andReturn(config('pagecache.cache_time', 86400));

    $this->middleware->handle($request, $nextClosure, 0, 'html');

    Bus::assertDispatched(RenderPage::class, function ($job) {
        $this->assertInstanceOf(\Carbon\Carbon::class, $job->delay);
        // Check that the job's delay timestamp is approximately 10 seconds from now (dispatch time)
        // Allow a small delta (e.g., 1-2 seconds) for processing time variations.
        $expectedTimestamp = now()->addSeconds(10)->timestamp;
        $this->assertEquals($expectedTimestamp, $job->delay->timestamp, '', 2);
        return true;
    });
});

?>
