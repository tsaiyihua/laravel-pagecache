<?php
return [
    'enable'        => env('PAGE_CACHE_ENABLE', true),
    'alive'         => env('PAGE_CACHE_ALIVE', 86400*15),
    'urlPattern'    => env('PAGE_CACHE_URL_PATTERN', '/^(http.*)\/\/([^\/]+)[\/]?([^\?]+)?\??(.*)?/'),
    'params'        => env('PAGE_CACHE_PARAMS', null),
    'delay'         => env('PAGE_CACHE_DELAY', 30),
    'owner'         => env('PAGE_CACHE_OWNER', 'nobody'),
    'group'         => env('PAGE_CACHE_GROUP', 'nobody')
];