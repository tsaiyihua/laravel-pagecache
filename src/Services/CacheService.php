<?php
namespace TsaiYiHua\Cache\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis as RedisManager;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use TsaiYiHua\Cache\Exceptions\PageCacheException;

/**
 * Class CacheService
 * @package TsaiYiHua\Cache
 */
class CacheService
{
    protected $urlPattern;
    protected $pageFile;
    protected $cachePath;
    protected $cacheTime;
    protected $url;

    protected $site;
    protected $path;
    protected $uri;
    protected $queryString;

    protected $contentType = 'html';

    public $statKey = 'pagecache:request';


    /**
     * Create Page Cache
     * @return bool
     */
    public function create()
    {
        $queryUrl = $this->site.$this->uri . "?nocache=1" . $this->queryString;
        try {
            $res = Http::withoutVerifying()->get($queryUrl);
        } catch (ConnectionException $e) {
            /** Do not stop the queue work while connection error */
            return false;
        }
        if ($res->status() == '200') {
            Storage::disk('pages')->put($this->pageFile, $res->body());
        } else {
            return false;
        }
        return true;
    }

    /**
     * Get the Page Cache Content
     * @return array
     */
    public function read()
    {
        $this->setCacheFile($this->url);
        $this->pageFile = $this->getCacheFile();
        try {
            $pageContent = Storage::disk('pages')->get($this->pageFile);
            $updateTime = Storage::disk('pages')->lastModified($this->pageFile);
        } catch(UnableToRetrieveMetadata $e){
            $pageContent = '';
            $updateTime = 0;
        } catch (FileNotFoundException $e) {
            $pageContent = '';
            $updateTime = 0;
        }
        return [
            'content'   => $pageContent,
            'update'    => $updateTime
        ];
    }

    /**
     * Delete Page cache file
     * @param null $url
     * @return bool
     */
    public function delete($url=null)
    {
        if ( $url == null ) $url = $this->url;
        $this->setCacheFile($url);
        $this->pageFile = $this->getCacheFile();
        return Storage::disk('pages')->delete($this->pageFile);
    }

    /**
     * Get the URL
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * formatted the URL
     * @param Request $request
     * @return $this
     */
    public function setUrl($request)
    {
        if ( $request->getPort() == '443' ) {
            $httpMode = "https://";
        } else {
            $httpMode = "http://";
        }
        $url = $httpMode.$request->getHttpHost()
            .$request->getPathInfo();
        $queryString = $request->getQueryString();
        $qString = $this->getCacheQueryString($queryString);
        if ( $qString ) $url .= '?'.$qString;

        if ( !preg_match($this->getUrlPattern(), $url) ) {
            throw new PageCacheException('Url format error');
        }
        $this->url = $url;
        return $this;
    }

    /**
     * Parse the URL and set the pageFile value
     * @param $url
     * @return $this
     */
    public function parseUrl($url)
    {
        if ( preg_match($this->getUrlPattern(), $url, $match) ) {
            $this->url = $url;
            $this->site = $match[1].'//'.$match[2];
            $this->path = $match[3];
            $this->uri = '/'.$this->path;
            $this->queryString = '&'.$match[4];
            $this->setCacheFile($url);
            $this->pageFile = $this->getCacheFile();
            return $this;
        } else {
            throw new PageCacheException('Url format error');
        }
    }

    /**
     * @param string $pattern
     * @return $this
     */
    public function setUrlPattern($pattern)
    {
        if ( !empty($pattern) ) {
            $this->urlPattern = $pattern;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getUrlPattern()
    {
        if ( empty($this->urlPattern)) {
            return config('pagecache.urlPattern');
        } else {
            return $this->urlPattern;
        }
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setCacheFile($url)
    {
        $cacheName = md5($url);
        $targetPath = substr($cacheName, -3,1).DIRECTORY_SEPARATOR
            .substr($cacheName, -2,1).DIRECTORY_SEPARATOR
            .substr($cacheName,-1,1);
        $targetFile = $targetPath.DIRECTORY_SEPARATOR.md5($url).".".$this->contentType;
        $this->pageFile = $targetFile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCacheFile()
    {
        return $this->pageFile;
    }

    /**
     * @param null|int $time, seconds
     * @return $this
     */
    public function setCacheTime($time=null)
    {
        if ( $time == null ) {
            $this->cacheTime = config('pagecache.alive');
        } else {
            $this->cacheTime = $time;
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getCacheTime()
    {
        return $this->cacheTime;
    }

    /**
     * Record request count to redis
     * @return bool
     */
    public function requestCount()
    {
        $key = $this->statKey.':'.date('Ymd');
        $hitCount = RedisManager::hGet($key, 'h');
        if ($hitCount === false) {
            $hashData = [
                'ttl'=>1,
                'h'=>0,
                'r'=>0
            ];
            RedisManager::hMset($key, $hashData);
        } else {
            RedisManager::hIncrby($key, 'ttl', 1);
        }
        return true;
    }

    /**
     * Page Cache Hit Count
     * @return bool
     */
    public function hitCount()
    {
        $key = $this->statKey.':'.date('Ymd');
        $hitCount = RedisManager::hGet($key, 'h');
        if ($hitCount == null) {
            $hashData = [
                'ttl'=>1,
                'h'=>1,
                'r'=>0
            ];
            RedisManager::hMset($key, $hashData);
            return true;
        } else {
            RedisManager::hIncrby($key, 'h', 1);
            return true;
        }
    }

    /**
     * Page Cache Refresh Count
     * @return bool
     */
    public function refreshCount()
    {
        $key = $this->statKey.':'.date('Ymd');
        $hitCount = RedisManager::hGet($key, 'h');
        if ($hitCount == null) {
            $hashData = [
                'ttl'=>1,
                'h'=>1,
                'r'=>1
            ];
            RedisManager::hMset($key, $hashData);
            return true;
        } else {
            RedisManager::hIncrby($key, 'r', 1);
            return true;
        }
    }

    /**
     * @param $date, date format is Ymd (YYYYMMDD)
     * @return array
     */
    public function getStatInfo($date)
    {
        $key = $this->statKey.':'.$date;
        $ttlCount = RedisManager::hGet($key, 'ttl');
        $hitCount = RedisManager::hGet($key, 'h');
        $refreshCount = RedisManager::hGet($key, 'r');
        $hitRate = 0;
        $refreshRate = 0;
        if ( !empty($ttlCount) ) {
            if ( $hitCount !== null ) {
                $hitRate = round($hitCount/$ttlCount,4)*100;
            }
            if ( $refreshCount !== null ) {
                $refreshRate = round($refreshCount/$ttlCount,4)*100;
            }
        }
        return [
            'total'     => $ttlCount,
            'hit'       => $hitCount,
            'refresh'   => $refreshCount,
            'hit rate'  => $hitRate."%",
            'refresh rate'  => $refreshRate."%"
        ];
    }

    public function setContentType($type)
    {
        $this->contentType = $type;
    }
    /**
     * @param $_SERVER['QUERY_STRING'] $queryString
     * @return false | $string
     */
    protected function getCacheQueryString($queryString)
    {
        if ( empty($queryString) ) return false;
        $queries = explode('&', $queryString);
        $q = array();
        foreach($queries as $query) {
            $buf = explode('=', $query);
            if ( !empty($buf) ) {
                if ( $this->isCacheParam($buf[0])) {
                    $q[] = $query;
                }
            }
        }
        return join('&', $q);
    }

    /**
     * @param $param
     * @return bool
     */
    protected function isCacheParam($param)
    {
        $registeredParameter = config('pagecache.params');
        if ( $registeredParameter !== null ) {
            $params = explode(",", $registeredParameter);
            if (in_array($param, $params)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
