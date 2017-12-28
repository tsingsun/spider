# yii2-spider

yii2-spider基于Yii2框架的爬虫应用,做为爬虫，经常需要用到各种组件，直接采用全家桶型框架，相比其他轻量的爬虫，免去后续组件的需求。  
采用Yii框架做为爬虫的运行框架，具有较好的可扩展性,支持守护模式(采用workerman)

## 特点

- 支持守护进程与普通两种模式（守护进程模式只支持 Linux 服务器）
- 默认使用 guzzle 进行爬取
- 支持分布式
- 支持内存、Redis 等多种队列方式
- 支持自定义URI过滤
- 支持广度优先和深度优先两种爬取方式
- 遵循 PSR-4 标准
- 爬取网页分为多步，每步均支持自定义动作（如添加代理、修改 user-agent 等）
- 灵活的扩展机制，可方便的为框架制作插件：自定义队列、自定义爬取方式...

## 安装

通过 composer 进行安装。

```
$ composer require tsingsun/yii2-spider
```

## 快速开始
```php
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');
require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

$configs = array(
    'taskNum' => 1,
    'seed' => array(
        'http://www.qiushibaike.com/'
    ),
    'urlFilter' => [
        '/http:\/\/www.qiushibaike.com\/article\/(\d*)/'
    ],
);

$option = [
    'id' => 'qiushibaike',
    'name' => '糗事百科',
    'basePath' => __DIR__,
    'logFile' => __DIR__ . '/qiushi.log',
    'daemonize' => true,
    'components' => [
        'spider' => $configs,
        'queue' => [
            'class' => 'tsingsun\spider\queue\RedisQueue',
            'name' => 'qiushi',
            'host' => 'localhost',
            'port' => 6379,
            'database' => 0,
        ],
        'client' => [
            'timeout' => 2,
        ],
    ],
];

$app = new \tsingsun\spider\Application($option);
$app->run();
```
在命令行中执行
```
$ php start.php
```
接下来就可以看到抓取的日志了。

## 事件
本个爬虫框架通过事件来响应爬虫的各过程

