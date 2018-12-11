<?php
namespace TsaiYiHua\Cache\Commands;

use Illuminate\Console\Command;
use TsaiYiHua\Cache\CacheService;

class CacheInfoCommand extends Command
{
    protected $signature = 'pagecache:info {url} {--date= : stat date}';
    protected $description = 'Get page cache info';


    protected $cacheSrv;

    public function __construct(CacheService $cacheSrv)
    {
        parent::__construct();
        $this->cacheSrv = $cacheSrv;
    }

    public function handle()
    {
        $url = $this->argument('url');
        if ( empty($url) ) {
            print "url can not leave be blank\n";
            die();
        }
        if ( $url == 'stat' ) {
            $date = $this->option('date');
            if ( empty($date) ) $date = date('Ymd', time());
            $data = $this->cacheSrv->getStatInfo($date);
            foreach($data as $key=>$val) {
                print $key.': '.$val."\n";
            }
        } else {
            $this->cacheSrv->parseUrl($url);
            $pageCacheInfo = $this->cacheSrv->read();
            if ($pageCacheInfo['content'] != '') {
                print "Page Cache : " . $this->cacheSrv->getCacheFile() . "\n";
                print "Update Time : " . date("Y-m-d H:i:s", $pageCacheInfo['update']) . "\n";
            } else {
                print 'No Cache' . "\n";
            }
        }
    }
}