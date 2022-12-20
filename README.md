## Laravel Page Cache

This Package is mainly used as a cached content on the entire page.  
If you had the low hit rate cache on your web page, this package will help you to solve this problem.

#### Basic Concept
 * When the accessed web page does not have cached content, the program content is directly executed, and the execution program that generates the physical webpage file is thrown into the queue to execute.
 * When the accessed web page has cached content, the cached content display is directly read.
 * When the accessed webpage has cached content, but the file generation time exceeds the set expiration time, the cache content display is directly read first, and then the execution program that generates the physical webpage file is thrown into the queue for execution.

#### Laravel Support

| Version  | Laravel Version |
|:---|:----------------|
| 1.0.x  | 5.6, 5.7, 5.8   |
| 1.1.x  | 6.x             |
| 2.x    | 7.x, 8.x, 9.x   |

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
 
 * Create temporary folder, suppose that you use the default setting in Laravel
 
 ```bash
   cd {YOUR PROJECT FOLDER}
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
    PAGE_CACHE_DELAY=30
    PAGE_CACHE_OWNER=nobody
    PAGE_CACHE_GROUP=nobody    
```
 * PAGE_CACHE_ENABLE boolean, page cache will work while this value is true
 * PAGE_CACHE_ALIVE timestamp, Page cache alive time, default is 15 days
 * PAGE_CACHE_URL_PATTERN regular expression, the default is common used, if you has the special rule, change this value.
 * PAGE_CACHE_PARAMS the query string who want to be cached, separate by ','
 * PAGE_CACHE_DISK The page cache file storage, if you use the default setting, don't set this variable in .env 
 * PAGE_CACHE_DELAY seconds, Create cache file after the page visited
 * PAGE_CACHE_OWNER The page cache file owner. only used while manage the cache file 
 * PAGE_CACHE_GROUP The page cache file group. only used while manage the cache file
 
 If you use the Cloud Storage, just ignore the PAGE_CACHE_OWNER and PAGE_CACHE_GROUP
 
#### APP_ENV in .env
  * While APP_ENV is "production", the request parameter "noCache" will be set as false to avoid slow the online version.
  * Base on the above reason
    * We suggest you have more than 2 servers, the APP_ENV on online version can be set as "production", and one of the servers can be set other word except "production" to generator the cache file.
    * If you have only one server, the APP_ENV can not be production. 
    * The cache generator server must edit /etc/hosts, and add the domain ip to 127.0.0.1

#### Commands
 * ```php artisan pagecache:clear```  
   Clear all page cache, if there had the permission problem, just use sudo to execute.
 * ```php artisan pagecache:refresh {url}```  
   Refresh the page cache by URL.
 * ```php artisan pagecache:info {url}```  
   Get the page cache info by URL
 * ```php artisan pagecache:info stat {--date= : stat date}```  
   If the {url} is state, it will show the stat info, the default date is today, you can use --date= to specify the date, format is Ymd.
   
     

