<?php

namespace TsaiYiHua\Cache\Http\Middleware;

use TsaiYiHua\Cache\CacheService;
use TsaiYiHua\Cache\Jobs\RenderPage;
use Carbon\Carbon;
use Closure;

class PageCache
{
    protected $cacheSrv;

    public function __construct(CacheService $cacheSrv)
    {
        $this->cacheSrv = $cacheSrv->setCacheTime();
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param int $cacheTime Cache alive time in seconds
     * @param string $contentType html|json當 url 為 stat 時，傳回統計資訊，預設為今天，可用 --date 來指定日期，日期格式為 Ymd
     * @return mixed
     */
    public function handle($request, Closure $next, $cacheTime=0, $contentType='html')
    {
        $cacheTime = intval($cacheTime);
        if ( $cacheTime > 0 ) $this->cacheSrv->setCacheTime($cacheTime);

        if ( config('app.env') != 'production' ) {
            $noCache = ($request->input('nocache') == 1) ? true : false;
        } else {
            $noCache = false;
        }
        if ( config('pagecache.enable') === false || $noCache === true) {
            return $next($request);
        }
        $this->cacheSrv->requestCount();
        $pageInfo = $this->cacheSrv->setUrl($request)->read();
        $pageContent = $pageInfo['content'];
        $updateTime = $pageInfo['update'];
        $expiredTime = $updateTime + $this->cacheSrv->getCacheTime();
        $expired = ( time() > $expiredTime ) ? true:false;
        $refresh = ( $request->input('refresh') == 1) ? true:false;

        $delaySeconds = config('pagecache.delay');
        if ( !empty($pageContent) ) {
            $this->cacheSrv->hitCount();
            if ( $expired || $refresh ) {
                $this->cacheSrv->refreshCount();
                RenderPage::dispatch($this->cacheSrv, $request)
                    ->delay(Carbon::now()->addSecond($delaySeconds));
            }
            if ( $contentType == 'json' ) {
                return response()->json(json_decode($pageContent),200);
            } else {
                return response()->make($pageContent, 200);
            }
        } else {
            RenderPage::dispatch($this->cacheSrv, $request)
                ->delay(Carbon::now()->addSecond($delaySeconds));
            return $next($request);
        }
    }
}
