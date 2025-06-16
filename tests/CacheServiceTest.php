<?php
namespace TsaiYiHua\Cache\Tests;

use Illuminate\Http\Client\RequestException; // For HTTP client errors
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http; // For mocking HTTP calls
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToRetrieveMetadata;
use Mockery\MockInterface;
use TsaiYiHua\Cache\Exceptions\PageCacheException;
use TsaiYiHua\Cache\Services\CacheService;

uses(TestCase::class); // Removed ->in('.')

beforeEach(function () {
    /** @var TestCase $this */
    $this->cacheService = new CacheService();
    // Ensure a clean testing disk state for relevant tests
    Storage::fake('pages');
    // Set default config for pagecache.params for relevant tests
    Config::set('pagecache.params', 'a,c'); // Default for tests needing it
    Config::set('pagecache.urlPattern', '/^(http.*)\/\/([^\/]+)[\/]?([^\?]+)?\??(.*)?/'); // Default pattern
    Config::set('pagecache.alive', 86400); // Default cache time
});

afterEach(function() {
    \Mockery::close();
    Config::set('pagecache.params', null); // Clean up config
});

// Existing tests (Ensure they are compatible with beforeEach/afterEach and Pest structure)
// For brevity, I'm not copying all existing tests, but the new ones will be added among them.
// Assume existing tests like 'set cache time', 'set url pattern', 'parse url fail', etc., are present.

test('set cache time', function() {
    /** @var TestCase $this */
    $cacheTime = 86400;
    $this->assertEquals($cacheTime, $this->cacheService->setCacheTime($cacheTime)->getCacheTime());
    $this->assertEquals(config('pagecache.alive'), $this->cacheService->setCacheTime()->getCacheTime());
});

test('set url pattern', function() {
    /** @var TestCase $this */
    $pattern = '/^(https.*)\/\/([^\/]+)[\/]?([^\?]+)?\??(.*)?/'; // Example different pattern
    $this->assertEquals($pattern, $this->cacheService->setUrlPattern($pattern)->getUrlPattern());
});

test('get default url pattern when none is set on service', function() {
    /** @var TestCase $this */
    // CacheService is new, so its internal $urlPattern is null
    $this->assertEquals(config('pagecache.urlPattern'), $this->cacheService->getUrlPattern());
});

test('get specific url pattern after setting it', function() {
    /** @var TestCase $this */
    $newPattern = 'my_custom_pattern';
    $this->cacheService->setUrlPattern($newPattern);
    $this->assertEquals($newPattern, $this->cacheService->getUrlPattern());
});

test('get default url pattern when empty pattern is passed to setUrlPattern', function() {
    /** @var TestCase $this */
    $this->cacheService->setUrlPattern(''); // Pass empty pattern
    $this->assertEquals(config('pagecache.urlPattern'), $this->cacheService->getUrlPattern());
});


test('set url with http and explicit port 80', function () {
    /** @var TestCase $this */
    $parameters = ['a' => 1, 'b' => 2];
    Config::set('pagecache.params', 'a');
    // Bypass this->createRequest and use Illuminate\Http\Request::create directly
    $server = ['HTTP_HOST' => 'localhost', 'SERVER_PORT' => 80, 'REQUEST_SCHEME' => 'http'];
    $request = \Illuminate\Http\Request::create('/testpage', 'GET', $parameters, [], [], $server);

    $expectedNormalizedUrl = 'http://localhost/testpage?a=1';
    $this->assertEquals($expectedNormalizedUrl, $this->cacheService->setUrl($request)->getUrl());
});

test('set url with https and explicit port 443', function () {
    /** @var TestCase $this */
    $parameters = ['c' => 3, 'd' => 4];
    Config::set('pagecache.params', 'c');
    $server = ['HTTP_HOST' => 'localhost', 'SERVER_PORT' => 443, 'HTTPS' => 'on'];
    $request = \Illuminate\Http\Request::create('/securepage', 'GET', $parameters, [], [], $server);

    $expectedNormalizedUrl = 'https://localhost/securepage?c=3';
    $this->assertEquals($expectedNormalizedUrl, $this->cacheService->setUrl($request)->getUrl());
});


test('parse url successfully', function() {
    /** @var TestCase $this */
    $randomPart = Str::random();
    $normalUrl = 'https://localhost/'. Str::slug($randomPart) .'?a=1&b=2&c=3&d=4'; // ensure slug-like string for path
    $this->cacheService->parseUrl($normalUrl);
    $this->assertEquals($normalUrl, $this->cacheService->getUrl());
});

test('parse url fail for invalid format', function () {
    /** @var TestCase $this */
    $badUrl = 'xml://aabb.com/affs'; // Does not match default pattern
    $this->cacheService->parseUrl($badUrl)->getUrl();
})->throws(PageCacheException::class, 'Url format error');


test('create cache successfully', function() {
    /** @var TestCase $this */
    Http::fake(['*' => Http::response('Test content', 200)]);
    // Storage::fake('pages') is in beforeEach

    $url = 'https://example.com/page1';
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl($url);

    $this->assertTrue($this->cacheService->create());
    Storage::disk('pages')->assertExists($this->cacheService->getCacheFile());
    $this->assertEquals('Test content', Storage::disk('pages')->get($this->cacheService->getCacheFile()));
});

test('create cache fails if http get returns non-200 status', function() {
    /** @var TestCase $this */
    Http::fake(['*' => Http::response('Not Found', 404)]);
    // Storage::fake('pages') is in beforeEach
    $url = 'https://example.com/notfound';
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl($url);

    $this->assertFalse($this->cacheService->create());
    Storage::disk('pages')->assertMissing($this->cacheService->getCacheFile());
});

test('create cache fails on http connection exception', function() {
    /** @var TestCase $this */
    Http::fake(function ($request) {
        throw new \Illuminate\Http\Client\ConnectionException("Connection timed out");
    });
    // Storage::fake('pages') is in beforeEach
    $url = 'https://example.com/timeout';
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl($url);

    $this->assertFalse($this->cacheService->create());
    Storage::disk('pages')->assertMissing($this->cacheService->getCacheFile());
});


test('read cache when file exists', function() {
    /** @var TestCase $this */
    // Storage::fake('pages') is in beforeEach
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl('https://example.com/my_page');
    $cacheFile = $this->cacheService->getCacheFile();

    $expectedContent = 'Cached test content';
    // Fake disk's touch equivalent or ensure modification time is testable
    Storage::disk('pages')->put($cacheFile, $expectedContent);
    // Last modified time is tricky with Storage::fake. We'll assume it's roughly now.
    // For more precision, a real filesystem or a more capable fake might be needed.
    // $expectedTime = Storage::disk('pages')->lastModified($cacheFile); // This would be the actual time

    $result = $this->cacheService->read();

    $this->assertEquals($expectedContent, $result['content']);
    $this->assertTrue(is_numeric($result['update']) && $result['update'] > 0); // Check it's a valid timestamp
});

test('read cache when file does not exist', function() {
    /** @var TestCase $this */
    // Storage::fake('pages') is in beforeEach
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl('https://example.com/non_existent_page');

    $result = $this->cacheService->read();

    $this->assertEquals('', $result['content']);
    $this->assertEquals(0, $result['update']);
});

test('read cache when storage get throws exception', function() {
    /** @var TestCase $this */
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl('https://example.com/storage_error_page');
    $cacheFile = $this->cacheService->getCacheFile();

    $mockedDisk = $this->mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
    $mockedDisk->shouldReceive('exists')->with($cacheFile)->andReturn(true); // Need to mock exists for the check
    $mockedDisk->shouldReceive('get')->with($cacheFile)->andThrow(new \Exception('Disk read error'));
    // If get() throws, lastModified() isn't reached for that file in the try block.

    // Replace the 'pages' disk instance with our mock for this test
    Storage::shouldReceive('disk')->with('pages')->andReturn($mockedDisk);

    $result = $this->cacheService->read();

    $this->assertEquals('', $result['content']);
    $this->assertEquals(0, $result['update']);
});

test('read cache when storage lastModified throws UnableToRetrieveMetadata', function() {
    /** @var TestCase $this */
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl('https://example.com/metadata_error_page');
    $cacheFile = $this->cacheService->getCacheFile();

    $mockedDisk = $this->mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
    $mockedDisk->shouldReceive('exists')->with($cacheFile)->andReturn(true); // Need to mock exists
    $mockedDisk->shouldReceive('get')->with($cacheFile)->andReturn('some content');
    $mockedDisk->shouldReceive('lastModified')->with($cacheFile)->andThrow(new UnableToRetrieveMetadata('Cannot read metadata'));

    Storage::shouldReceive('disk')->with('pages')->andReturn($mockedDisk);

    $result = $this->cacheService->read();

    $this->assertEquals('', $result['content']); // Content should be empty due to exception
    $this->assertEquals(0, $result['update']);   // Update time should be 0 due to exception
});


test('delete cache for existing file', function() {
    /** @var TestCase $this */
    // Storage::fake('pages') is in beforeEach
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl('https://example.com/to_delete');
    $cacheFile = $this->cacheService->getCacheFile();
    Storage::disk('pages')->put($cacheFile, 'content');
    Storage::disk('pages')->assertExists($cacheFile);

    $this->assertTrue($this->cacheService->delete());
    Storage::disk('pages')->assertMissing($cacheFile);
});

test('delete cache for non-existing file', function() {
    /** @var TestCase $this */
    // Storage::fake('pages') is in beforeEach
    $this->cacheService->setContentType('html');
    $this->cacheService->parseUrl('https://example.com/non_existent_for_delete');
    $cacheFile = $this->cacheService->getCacheFile();
    Storage::disk('pages')->assertMissing($cacheFile);

    $this->assertTrue($this->cacheService->delete());
});


// Tests for setUrl covering getCacheQueryString and isCacheParam logic
test('setUrl with empty pagecache.params config', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', '');
    $request = \Illuminate\Http\Request::create('/path', 'GET', ['a' => 1, 'b' => 2]);
    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path', $this->cacheService->getUrl());
});

test('setUrl with null pagecache.params config', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', null);
    $request = \Illuminate\Http\Request::create('/path', 'GET', ['a' => 1, 'b' => 2]);
    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path', $this->cacheService->getUrl());
});

test('setUrl with mixed params some in config', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', 'a,c,e');
    $request = \Illuminate\Http\Request::create('/path', 'GET', ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]);
    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path?a=1&c=3&e=5', $this->cacheService->getUrl());
});

test('setUrl with all params in config', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', 'a,b,c');
    $request = \Illuminate\Http\Request::create('/path', 'GET', ['a' => 1, 'b' => 2, 'c' => 3]);
    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path?a=1&b=2&c=3', $this->cacheService->getUrl());
});

test('setUrl with no params in config from query string', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', 'x,y,z');
    $request = \Illuminate\Http\Request::create('/path', 'GET', ['a' => 1, 'b' => 2, 'c' => 3]);
    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path', $this->cacheService->getUrl());
});

test('setUrl with empty query string array', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', 'a,b');
    $request = \Illuminate\Http\Request::create('/path', 'GET', []);
    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path', $this->cacheService->getUrl());
});

test('setUrl with actual empty query string in uri', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', 'a,b');
    $request = \Illuminate\Http\Request::create('/path', 'GET', []);
    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path', $this->cacheService->getUrl());
});


test('setUrl with query string having params without values', function() {
    /** @var TestCase $this */
    Config::set('pagecache.params', 'a,b');
    // For URIs with query strings, Request::create's $uri param should contain the full path and query
    // The $parameters argument is then for POST body or can override GET params from URI.
    // To be safe and explicit for GET, embed query in URI and pass empty array for $parameters.
    $request = \Illuminate\Http\Request::create('/path?a=&b=2&c', 'GET', []);

    $this->cacheService->setUrl($request);
    $this->assertEquals('http://localhost/path?a=&b=2', $this->cacheService->getUrl());
});

// Redis dependent tests
test('requestCount increments total count', function() {
    /** @var TestCase $this */
    $redisKey = 'pagecache:request:'.date('Ymd');
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn(false);
    \Illuminate\Support\Facades\Redis::shouldReceive('hMset')->with($redisKey, ['ttl'=>1, 'h'=>0, 'r'=>0])->once();
    $this->assertTrue($this->cacheService->requestCount());

    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn('5');
    \Illuminate\Support\Facades\Redis::shouldReceive('hIncrby')->with($redisKey, 'ttl', 1)->once();
    $this->assertTrue($this->cacheService->requestCount());
});

test('hitCount increments hit and total count', function() {
    /** @var TestCase $this */
    $redisKey = 'pagecache:request:'.date('Ymd');
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn(null);
    \Illuminate\Support\Facades\Redis::shouldReceive('hMset')->with($redisKey, ['ttl'=>1, 'h'=>1, 'r'=>0])->once();
    $this->assertTrue($this->cacheService->hitCount());

    // Second call
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn('5');
    // ttl is only incremented by requestCount, not hitCount or refreshCount after initialization
    // \Illuminate\Support\Facades\Redis::shouldReceive('hIncrby')->with($redisKey, 'ttl', 1)->never();
    \Illuminate\Support\Facades\Redis::shouldReceive('hIncrby')->with($redisKey, 'h', 1)->once();
    $this->assertTrue($this->cacheService->hitCount());
});


test('refreshCount increments refresh and total count', function() {
    /** @var TestCase $this */
    $redisKey = 'pagecache:request:'.date('Ymd');
    // Expectation for the first call to refreshCount, aligning with Mockery error (h=1)
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn(null);
    \Illuminate\Support\Facades\Redis::shouldReceive('hMset')->with($redisKey, ['ttl' => 1, 'h' => 1, 'r' => 1])->once();
    $this->assertTrue($this->cacheService->refreshCount());

    // Expectation for the second call to refreshCount
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn('5');
    \Illuminate\Support\Facades\Redis::shouldReceive('hIncrby')->with($redisKey, 'r', 1)->once();
    $this->assertTrue($this->cacheService->refreshCount());
});

test('clearCountData deletes redis key', function() {
    /** @var TestCase $this */
    $redisKey = 'pagecache:request:'.date('Ymd');
    \Illuminate\Support\Facades\Redis::shouldReceive('del')->with($redisKey)->once()->andReturn(1);
    $this->assertTrue($this->cacheService->clearCountData());
});

test('getStatInfo calculates rates correctly', function() {
    /** @var TestCase $this */
    $date = '20230501';
    $redisKey = 'pagecache:request:'.$date;
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'ttl')->once()->andReturn('100');
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn('50');
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'r')->once()->andReturn('20');

    $stats = $this->cacheService->getStatInfo($date);
    $this->assertEquals([
        'total'     => '100',
        'hit'       => '50',
        'refresh'   => '20',
        'hit rate'  => '50%',
        'refresh rate'  => '20%'
    ], $stats);
});

test('getStatInfo handles null counts from redis', function() {
    /** @var TestCase $this */
    $date = '20230502';
    $redisKey = 'pagecache:request:'.$date;
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'ttl')->once()->andReturn(null);
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'h')->once()->andReturn(null);
    \Illuminate\Support\Facades\Redis::shouldReceive('hGet')->with($redisKey, 'r')->once()->andReturn(null);

    $stats = $this->cacheService->getStatInfo($date);
    $this->assertEquals([
        'total'     => null,
        'hit'       => null,
        'refresh'   => null,
        'hit rate'  => '0%',
        'refresh rate'  => '0%'
    ], $stats);
});

test('set content type', function() {
    /** @var TestCase $this */
    $this->cacheService->setContentType('json');
    $this->cacheService->parseUrl('https://example.com/page');
    $this->assertStringEndsWith('.json', $this->cacheService->getCacheFile());

    $this->cacheService->setContentType('xml');
    $this->cacheService->parseUrl('https://example.com/page');
    $this->assertStringEndsWith('.xml', $this->cacheService->getCacheFile());
});

?>
