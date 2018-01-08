# yii2-spider

yii2-spider基于Yii2框架的爬虫应用,做为爬虫，经常需要用到各种组件，直接采用全家桶型框架，相比其他轻量的爬虫，免去后续组件的需求。  
采用Yii框架做为爬虫的运行框架，具有较好的可扩展性,支持守护模式(采用workerman)

## 特点

- 支持守护进程与普通两种模式（守护进程模式只支持 Linux 服务器）
- 支持分布式
- 支持array(调试)、Redis 等多种队列方式
- 支持自定义URI过滤
- 支持广度优先和深度优先两种爬取方式
- 基于事件的流程处理
- 内置数据解析与导出

## 安装

通过 composer 进行安装。

```
$ composer require tsingsun/yii2-spider
```

## 快速开始
```php
require(__DIR__ . '/../vendor/autoload.php');

$configs = array(
    'taskNum' => 1,
    'seed' => array(
        'http://www.qiushibaike.com/'
    ),
    'urlFilter' => [
        '/http:\/\/www.qiushibaike.com\/article\/(\d*)/'
    ],
    'as parseData' => [
        'class' => 'tsingsun\spider\parser\Parser',
        'contentUrlFilter' => "#http://www.qiushibaike.com/article/\d+#",
        'fields' => [
            'article_title' => [
                'selector' => "//*[@id='single-next-link']//div[contains(@class,'content')]/text()[1]",
                'required' => true,
            ],
            'article_author' => [
                'selector' => "//div[contains(@class,'author')]//h2",
                'required' => true,

            ],
            'article_headimg' => [
                'selector' => "//div[contains(@class,'author')]//a[1]",
                'required' => true,

            ],
            'article_content' => [
                'selector' => "//*[@id='single-next-link']//div[contains(@class,'content')]",
                'required' => true,
            ],
            'article_publish_time' => [
                'selector' => "//div[contains(@class,'author')]//h2",
                'required' => true,
            ],
            'url' => [
                'default' => "url",                
                'required' => true,

            ],
        ]
    ],
    'as exportData'=>[
        'class'=>'tsingsun\spider\export\Export',
        'exportType'=>'cvs',
        'exportFile'=>'qiushibaike.cvs'
    ],
);

$option = [
    'id' => 'qiushibaike',
    'name' => '糗事百科',
    'basePath' => __DIR__,    
    'daemonize' => true,
    'components' => [
        'spider' => $configs,
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => 'localhost',
            'port' => 6379,
            'database' => 0,
        ],
        'queue' => [
            'name' => 'qiushi',
        ],
        'client' => [
            'timeout' => 2,
        ],
    ],
];

$hotApp = \tsingsun\spider\SpiderCreator::create($option);
$hotApp->run();

```
在命令行中执行
```
$ php start.php
```
接下来就可以看到抓取的日志了。

## 技术点
- 使用[yii 框架](http://www.yiichina.com/doc/guide/2.0),使用者只需要知道如何配置组件即可
- [dom-crawler](http://symfony.com/doc/current/components/dom_crawler.html) 页面分析,支持xpath,css选择器
- goutte 用于封装网页请求,结合dom-crawler使用
- guzzle\client http请求客户端
- beanbun 参考了该框架对workerman的使用与redisQueue的实现

## 具体介绍
[使用说明](./docs/intro.md)



