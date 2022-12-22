<?php
namespace TsaiYiHua\Cache\Tests;

use Illuminate\Support\Facades\Config;
use TsaiYiHua\Cache\Exceptions\PageCacheException;
use TsaiYiHua\Cache\Services\CacheService;

test('artisan pagecache:clear', function() {
    $this->artisan('pagecache:clear')->assertSuccessful();
});

test('artisan pagecache:info', function() {
    $cacheSrv = new CacheService();
    $url = 'https://tyh.idv.tw/bookmark';
    $this->artisan('pagecache:info '.$url)->assertSuccessful();
    $this->artisan('pagecache:info ""')->assertExitCode(1);
    $this->artisan('pagecache:info stat')->assertSuccessful(1);
    $cacheSrv->parseUrl($url)->create();
    $this->artisan('pagecache:info '.$url)->assertSuccessful();
});

test('artisan pagecache:refresh', function() {
    $url = 'https://tyh.idv.tw/bookmark';
    Config::set('pagecache.owner', 'tyh');
    Config::set('pagecache.group', 'tyh');
    $this->artisan('pagecache:refresh '.$url)->assertSuccessful();
    $this->artisan('pagecache:refresh ""')->assertExitCode(1);
    $this->artisan('pagecache:clear')->assertSuccessful();
});

test('artisan pagecache:refresh page not found', function() {
    $url = 'https://tyh.idv.tw/bookmark';
    Config::set('pagecache.owner', 'root');
    Config::set('pagecache.group', 'root');
    $this->artisan('pagecache:refresh '.$url);
})->throws(PageCacheException::class);

test('artisan pagecache:refresh page not found but create', function() {
    $url = 'https://tyh.idv.tw/bookmark';
    Config::set('pagecache.owner', 'root');
    Config::set('pagecache.group', 'root');
    $this->artisan('pagecache:refresh '.$url.' --create')->assertSuccessful();
});
