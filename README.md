## Laravel Page Cache

This Package is mainly used as a cached content on the entire page.  
If you had the low hit rate cache on your web page, this package will help you to solve this problem.

#### Basic Concept
 * When the accessed web page does not have cached content, the program content is directly executed, and the execution program that generates the physical webpage file is thrown into the queue to execute.
 * When the accessed web page has cached content, the cached content display is directly read.
 * When the accessed webpage has cached content, but the file generation time exceeds the set expiration time, the cache content display is directly read first, and then the execution program that generates the physical webpage file is thrown into the queue for execution.

#### Install
 * ```composer require tsaiyihua/laravel-pagecache```
 * ```php artisan vendor:publish --tag=pagecache```
 * Add disk to config/filesystems.php
 
 ```php
         'pages' => [
             'driver' => 'local',
             'root' => env('PAGE_CACHE_DISK', storage_path('app/pages')),
         ],
 ```
 * Add middleware to $routeMiddleware in app/Http/kernel.php
 
 ```php
    'pageCache' => \TsaiYiHua\Cache\Http\Middleware\PageCache::class
 ```
 * Create temporary folder
 
 ```bash
   mkdir storage/app/pages
   chmod 777 storage/app/pages
 ```
 * And then add "pageCache" middleware to your route where you want to cache page

OK, It's done. Join it. 

#### .env variable descriptions 
```php
    PAGE_CACHE_ENABLE=true
    PAGE_CACHE_ALIVE=1296000
    PAGE_CACHE_URL_PATTERN=/^(http.*)\/\/([^\/]+)[\/]?([^\?]+)?\??(.*)?/
    PAGE_CACHE_PARAMS=l,p
    PAGE_CACHE_DISK=/cache/pages
    PAGE_CACHE_DELAY=30
    PAGE_CACHE_OWNER=nobody
    PAGE_CACHE_GROUP=nobody    
```
 * PAGE_CACHE_ENABLE boolean, page cache will work while this value is true
 * PAGE_CACHE_ALIVE timestamp, Page cache alive time, default is 15 days
 * PAGE_CACHE_URL_PATTERN regular expression, the default is common used, if you has the special rule, change this value.
 * PAGE_CACHE_PARAMS the query string who want to be cached, separate by ','
 * PAGE_CACHE_DISK The page cache file storage 
 * PAGE_CACHE_DELAY seconds, Create cache file after the page visited
 * PAGE_CACHE_OWNER The page cache file owner
 * PAGE_CACHE_GROUP The page cache file group
 
#### Commands
 * ```php artisan pagecache:clear```  
   Clear all page cache, if there had the permission problem, just use sudo to execute.
 * ```php artisan pagecache:refresh {url}```  
   Refresh the page cache by URL.
 * ```php artisan pagecache:info {url}```  
   Get the page cache info by URL
 * ```php artisan pagecache:info stat {--date= : stat date}```  
   If the {url} is state, it will show the stat info, the default date is today, you can use --date= to specify the date, format is Ymd.
   
     

