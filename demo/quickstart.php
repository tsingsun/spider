<?php

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
                'name' => "url",
                'selector' => "//div[contains(@class,'author')]//h2",   // 这里随便设置，on_extract_field回调里面会替换
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
    'logFile' => __DIR__ . '/qiushi.log',
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
//            'class'=>'tsingsun\spider\queue\RedisQueue',
            'name' => 'qiushi',
//            'host'=>'localhost',
//            'port' => 6379,
//            'database' => 0,
        ],
        'client' => [
            'timeout' => 2,
        ],
    ],
];

$hotApp = \tsingsun\spider\SpiderCreator::create($option);
$hotApp->run();


