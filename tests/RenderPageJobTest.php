<?php
namespace TsaiYiHua\Cache\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use TsaiYiHua\Cache\Jobs\RenderPage;
use TsaiYiHua\Cache\Services\CacheService;

test('render page job', function() {
    Bus::fake();
    $cacheSrv = new CacheService();
    $parameters = [
        'a' => 1,
        'b' => 2,
        'c' => 3,
        'd' => 4
    ];
    $url = 'https://localhost/'.Str::random();
    $request = $this->createRequest('get', Str::random(32), $url, [], $parameters);
    RenderPage::dispatchSync($cacheSrv, $request);
    Bus::assertDispatched(RenderPage::class);
});
