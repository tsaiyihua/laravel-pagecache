## Laravel Page Cache

這個Cache主要是以整頁實體的暫存網頁當做快取內容。  

#### 基本概念
 * 當訪問的網頁沒有快取內容時，先直接執行程式內容，再把產生實體網頁檔的執行程序丟到佇列去執行。
 * 當訪問的網頁有快取內容時，直接讀取快取內容顯示。
 * 當訪問的網頁有快取內容，但其檔案產生時間走過設定的到期時間時，一樣先直接讀取快取內容顯示，再把產生實體網頁檔的執行程序丟到佇列去執行。
 
#### Laravel 支援

| Version  | Laravel Version  |
|:---|:---|
| 1.0.x  | 5.6, 5.7, 5.8   |
| 1.1.x  | 6.x  |
| 2.x    | 7.x |
 
#### 安裝
 * ```composer require tsaiyihua/laravel-pagecache```
 * ```php artisan vendor:publish --tag=pagecache```
 * 在 config/filesystems.php 裡的 disk 下加入
 
 ```php
         'pages' => [
             'driver' => 'local',
             'root' => env('PAGE_CACHE_DISK', storage_path('app/pages')),
         ],
 ```
 * 在 app/Http/kernel.php 裡的 $routeMiddleware 下加入
 
 ```php
    'pageCache' => \TsaiYiHua\Cache\Http\Middleware\PageCache::class
 ```
 
 * 建立暫存資料夾，這裡假設您使用laravel預設的storage
 
 ```bash
   cd {YOUR PROJECT FOLDER}
   mkdir storage/app/pages
   chmod 777 storage/app/pages
 ```
 * 接著就是在你要運行 PageCache 的地方加上 pageCache 的 middleware
 
以上設定完成，基本上就可以run了。  

可以在 .env 設定其環境變數，可用的設定如下
```php
    PAGE_CACHE_ENABLE=true
    PAGE_CACHE_ALIVE=1296000
    PAGE_CACHE_URL_PATTERN=/^(http.*)\/\/([^\/]+)[\/]?([^\?]+)?\??(.*)?/
    PAGE_CACHE_PARAMS=l,p
    PAGE_CACHE_DELAY=30
    PAGE_CACHE_OWNER=nobody
    PAGE_CACHE_GROUP=nobody    
```
 * PAGE_CACHE_ENABLE 為 true 或 false ，當其為 true 才啟動 Page Cache 機制
 * PAGE_CACHE_ALIVE 為 Cache 時間(秒數)，預設為15天
 * PAGE_CACHE_URL_PATTERN 為 URL 的格式設定（不要去自己設定比較好）
 * PAGE_CACHE_PARAMS 可被 Cache 的參數設定， 以逗號隔開。
 * PAGE_CACHE_DISK 暫存檔存放的資料夾，預設為 storage/app/pages。用預設值時，不用此變數。
 * PAGE_CACHE_DELAY 網頁執行時，幾秒後開始建立該頁暫存檔
 * PAGE_CACHE_OWNER 暫存檔的系統擁有者。管理暫存檔時才會用到。
 * PAGE_CACHE_GROUP 暫存檔的系統群組。管理暫存檔時才會用到。

#### APP_ENV 設定
 * 當 APP_ENV 為 production 時，URL的 noCache 參數會被強制設為 false，以避免被直接在線上發出需求而拖慢網頁速度
 * 由於上述原因
   * 建議兩台以上的伺服器來做服務，線上運行版本可將 APP_ENV 設為 production，而指定其中一台不對外服務，APP_ENV 可設為 production 以外的任何字串，專門來產生暫存檔。
   * 如果只有一台，則線上版本不能將 APP_ENV 設為 production
   * 專門產生暫存檔的伺服器，則在 /etc/hosts 內，將網域 IP 設為 127.0.0.1

#### 可使用指令
 * ```php artisan pagecache:clear```  
    清除所有 page cache, 如果有檔案權限問題， 則用 sudo 來執行。
 * ```php artisan pagecache:refresh {url}```  
    更新指定URL的 page cache 。
 * ```php artisan pagecache:info {url}```  
    取得指定URL的 page cache 資訊。
 * ```php artisan pagecache:info stat {--date= : stat date}```
    當 {url} 為 stat 時，傳回統計資訊，預設為今天，可用 --date 來指定日期，日期格式為 Ymd
     
