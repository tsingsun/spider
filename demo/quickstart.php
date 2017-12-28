<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/26
 * Time: 下午2:09
 */

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');
require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');
//采集源配置
$configs = array(
    'taskNum' => 1,
    'seed' => array(
        'http://www.qiushibaike.com/'
    ),
    'urlFilter' => [
        '/http:\/\/www.qiushibaike.com\/article\/(\d*)/'
    ],
);
//Yii 组件配置
$option = [
    'id' => 'qiushibaike',
    'name' => '糗事百科',
    'basePath' => __DIR__,
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
$app->getSpider()->on(\tsingsun\spider\Spider::EVENT_AFTER_DOWNLOAD_PAGE,function($event){
    file_put_contents(__DIR__ . '/' . md5($event->sender->url), $event->sender->page);
});
$app->run();