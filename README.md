### 安装

```php
    # 添加源
    {
        "type": "git",
        "url": "https://gitlab.yilu.co/yilu/laravel-sharecache.git"
    }

    composer require yilu-dev/sharecache
```

### 配置信息

1.生成配置文件

`php artisan vendor:publish --tag=sharecache-config`

2.配置信息
```php
   [
        'name' => 'server_name', // 服务名称

        'url' => env('APP_URL'), // 服务地址
        
        'cache' => [
            'prefix' => 'sharecache',
            'ttl' => 1209600
        ],
    
        'route_option' => [
    
        ],
        
        // 默认获取 $model->toJson() 作为缓存对象， 自定义请定义 getShareCacheData()
        'models' => [       // 'alias' => 'class'
    
        ],
    
        // 注册模型做为监听模型， 必须定义 getShareCacheData()
        'repositories' => [
    
        ]
   ]
```

3.注册文件

`php artisan sharecache:register`

4.查看注册信息

`php artisan sharecache:show`

5.清除缓存
```
php artisan sharecache:flush

--server=*  // 服务名字, 为空清除所有服务下的对象

--object=*  // 缓存对象, 为空清除服务下的所有对象

--except    // 对前面条件取反
```

### 使用

```php

ShareCache::service($name = null)->get($object, $key) // $name=服务名称,默认取当前服务； $object=缓存对象名称； $key=缓存对象key

ShareCache::{$server}()->get($object, $key) // $server=服务名称

```
