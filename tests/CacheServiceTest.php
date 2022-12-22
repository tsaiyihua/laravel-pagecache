<?php
namespace TsaiYiHua\Cache\Tests;

use Illuminate\Support\Str;
use TsaiYiHua\Cache\Exceptions\PageCacheException;
use TsaiYiHua\Cache\Services\CacheService;
use Illuminate\Support\Facades\Config;

//class CacheServiceTest extends TestCase
//{
//    protected CacheService $cacheSrv;
//    public function __construct(?string $name = null, array $data = [], $dataName = '')
//    {
//        parent::__construct($name, $data, $dataName);
//        $this->cacheSrv = new CacheService();
//    }
//
//    public function test_set_cache_time()
//    {
//        $this->cacheSrv->setCacheTime();
//        $getCacheTime = $this->cacheSrv->getCacheTime();
//        $this->assertEquals(config('pagecache.alive'), $getCacheTime);
//    }
//}

uses(TestCase::class)->in('.');

beforeEach(function () {
    /** @var TestCase $this */
    $this->cacheService = new CacheService();
});

test('set cache time', function() {
    $cacheTime = 86400;
    $this->assertEquals($cacheTime, $this->cacheService->setCacheTime($cacheTime)->getCacheTime());
    $this->assertEquals(config('pagecache.alive'), $this->cacheService->setCacheTime()->getCacheTime());
});

test('set url pattern', function() {
    $pattern = '/^(http.*)\/\/([^\/]+)[\/]?([^\?]+)?\??(.*)?/';
    $this->assertEquals(config('pagecache.urlPattern'), $this->cacheService->setUrlPattern($pattern)->getUrlPattern());
});

test('set url', function () {
    $parameters = [
        'a' => 1,
        'b' => 2,
        'c' => 3,
        'd' => 4
    ];
    $url = 'https://localhost/'.Str::random();
    $httpUrl = 'http://localhost/'.Str::random();
    $request = $this->createRequest('get', Str::random(32), $url, [], $parameters);
    $httpRequest = $this->createRequest('get', Str::random(32), $httpUrl, [], $parameters);

    /** No Parameter */
    $this->assertEquals($url, $this->cacheService->setUrl($request)->getUrl());
    $this->assertEquals($httpUrl, $this->cacheService->setUrl($httpRequest)->getUrl());

    /** Has Parameters */
    Config::set('pagecache.params', 'b,d');
    $this->assertEquals($url.'?b=2&d=4', $this->cacheService->setUrl($request)->getUrl());
});

test('parse url', function() {
    $normalUrl = 'https://localhost/'.Str::random().'?a=1&b=2&c=3&d=4';
    $this->assertEquals($normalUrl, $this->cacheService->parseUrl($normalUrl)->getUrl());
});

test('parse url fail', function () {
    $badUrl = 'xml://aabb.com/affs';
    $this->cacheService->parseUrl($badUrl)->getUrl();
})->throws(PageCacheException::class);

test('create cache', function() {
    $url = 'https://tyh.idv.tw/bookmark';
    $this->cacheService->setContentType('html');
    $this->assertTrue($this->cacheService->parseUrl($url)->create());
});

test('create cache fail with connection', function() {
    $url = 'https://host.cannot.connect';
    $this->assertFalse($this->cacheService->parseUrl($url)->create());
});

test('create cache fail with page not found', function() {
    $url = 'https://tyh.idv.tw/aabbbcccddd';
    $this->assertFalse($this->cacheService->parseUrl($url)->create());
});
test('read cache', function() {
    $url = 'https://tyh.idv.tw/bookmark';
    $request = $this->createRequest('get', '', $url);
    $cacheContent = $this->cacheService->setUrl($request)->read();
    $this->assertIsInt($cacheContent['update']);
});

test('can not read cache', function() {
    $url = 'https://tyh.idv.tw/guestbook';
    $request = $this->createRequest('get', '', $url);
    $cacheContent = $this->cacheService->setUrl($request)->read();
    $this->assertEquals($cacheContent['update'], 0);
});

test('delete cache', function() {
    $url = 'https://tyh.idv.tw/bookmark';
    $request = $this->createRequest('get', '', $url);
    $this->assertTrue($this->cacheService->setUrl($request)->delete());
});

test('request count', function() {
    $this->assertTrue($this->cacheService->clearCountData());
    $this->assertTrue($this->cacheService->requestCount());
    $this->assertTrue($this->cacheService->requestCount());
});

test('hit count', function() {
    $this->assertTrue($this->cacheService->clearCountData());
    $this->assertTrue($this->cacheService->hitCount());
    $this->assertTrue($this->cacheService->hitCount());
});

test('refresh count', function() {
    $this->assertTrue($this->cacheService->clearCountData());
    $this->assertTrue($this->cacheService->refreshCount());
    $this->assertTrue($this->cacheService->refreshCount());
});

test('get stat info', function() {
    $this->cacheService->requestCount();
    $this->cacheService->hitCount();
    $statInfo = $this->cacheService->getStatInfo(date('Ymd'));
    $this->assertArrayHasKey('total', $statInfo);
});
