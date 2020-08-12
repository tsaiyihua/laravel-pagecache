<?php

namespace TsaiYiHua\Cache\Jobs;

use Illuminate\Http\Request;
use TsaiYiHua\Cache\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RenderPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var CacheService
     */
    protected $cacheSrv;
    /**
     * Create a new job instance.
     *
     * @param CacheService $cacheSrv
     * @param Request $request
     *
     * @return void
     */
    public function __construct($cacheSrv, $request)
    {
        $this->cacheSrv = $cacheSrv->setUrl($request);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->cacheSrv->parseUrl($this->cacheSrv->getUrl())->create();
    }
}
