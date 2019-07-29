<?php
namespace TsaiYiHua\Cache\Commands;

use Illuminate\Console\Command;
use TsaiYiHua\Cache\CacheService;
use TsaiYiHua\Cache\Exceptions\PageCacheException;

class CacheRefreshCommand extends Command
{
    protected $signature = 'pagecache:refresh {url} 
                            {--create : if not found, create it}';
    protected $description = 'Refresh the specified page cache';

    protected $cacheSrv;

    protected $cloudStorage;
    
    public function __construct(CacheService $cacheSrv)
    {
        parent::__construct();
        $this->cacheSrv = $cacheSrv;
        $this->cloudStorage = ['s3', 'gcs'];
    }

    public function handle()
    {
        $url = $this->argument('url');
        if ( empty($url) ) {
            print "url can not leave be blank\n";
            die();
        }
        $create = $this->option('create');
        $cacheFileOwner = config('pagecache.owner');
        $cacheFileGroup = config('pagecache.group');
        $this->cacheSrv->parseUrl($url);
        if ( $this->cacheSrv->read()['content'] != '' ) {
            $this->cacheSrv->create();
            $cacheFile = config('filesystems.disks.pages.root')
                .DIRECTORY_SEPARATOR.$this->cacheSrv->getCacheFile();
            if (!in_array(config('filesystems.disks.pages.driver'), $this->cloudStorage)) {
                chown($cacheFile, $cacheFileOwner);
                chgrp($cacheFile, $cacheFileGroup);
            }
        } else {
            if ( $create ) {
                $this->cacheSrv->create();
                print "page cache has been created\n";
            } else {
                throw new PageCacheException('Cache file not found');
            }
        }
        if ( !$create ) {
            print "page cache has been updated\n";
        }
    }
}