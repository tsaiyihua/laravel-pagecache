<?php
namespace TsaiYiHua\Cache\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use TsaiYiHua\Cache\Exceptions\PageCacheException;

class CacheCommand extends Command
{
    protected $signature = 'pagecache:clear';
    protected $description = 'Clear all page cache';

    public function handle()
    {
        $dirs = Storage::disk('pages')->directories();
        foreach ( $dirs as $dir) {
            $rs = Storage::disk('pages')->deleteDirectory($dir);
            if ( $rs === false ) {
                throw new PageCacheException('Can not remove directory '.$dir);
            }
        }
        print "page cache has been cleared\n";
    }
}