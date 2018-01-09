<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/28
 * Time: 下午5:08
 */

require_once(__DIR__ . '/../vendor/autoload.php');
use tsingsun\spider\Spider;

$configs = array(
    'taskNum' => 1,
    'seed' => [
        'http://tech.sina.com.cn/i/2018-01-03/doc-ifyqefvx5765525.shtml',
    ],
    'urlFilter' => [
        '#^http://tech.sina.com.cn/[\w\d/-]*\.shtml#'
    ],
    'as parseData'=>[
        'class'=>'tsingsun\spider\parser\Parser',
        'contentUrlFilter'=>"#^http://tech.sina.com.cn/[\w\d/-]*\.shtml#",
        'fields'=>[
            'article_title' => [
                'selector' => "/html/body/div[6]/h1",
                'required' => true,
            ],
            'article_author' => [
                'selector' => "//div[contains(@class,'author')]//h2",
                'required' => true,

            ],
            'article_headimg' => [
                'selector' => "//*[@id=\"artibody\"]//img",
                'required' => true,
            ],
            'article_content' => [
                'selector' => "//*[@id=\"artibody\"]",
                'required' => true,
                'callback' =>'fixContent',
            ],
            'article_publish_time' => [
                'selector' => "//*[@id=\"top_bar\"]/div/div[2]/span[1]",
                'required' => true,
            ],
            'url' => [
                'default' => 'url',   // 这里随便设置，on_extract_field回调里面会替换
                'required' => true,
            ],
        ],
    ],
    'as exportData' =>[
        'class'=>'tsingsun\spider\export\Export',
        'exportType'=>'cvs',
        'exportFile'=>'sinait.cvs',
    ],
);

$option = [
    'id' => 'sinait',
    'name' => '新浪科技',
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
$spider = $hotApp->getSpider();

function sinaFirst()
{
    $url = 'http://feed.mix.sina.com.cn/api/roll/get?pageid=372&lid=2431&k=&num=50&page=1';
    /** @var \GuzzleHttp\Client $client */
    $client = Yii::$app->get('client');
    $res = $client->request('GET',$url,[
        'content_type'=>'application/json',
        'Accept-Encoding' => 'gzip',
    ]);
    $json = json_decode($res->getBody(),true);
    /** @var Spider $spider */
    $spider = Yii::$app->getSpider();
    if($data = $json['result']['data']??false){
        foreach ($data as $item){
            $rq = new \tsingsun\spider\RequestItem(['url'=>$item['url']]);
            if(!$spider->queue()->isQueued($rq)){
                $spider->queue()->add($rq);
                continue;
            }
        }
    }
}

/**
 * @param \Symfony\Component\DomCrawler\Crawler $crawler
 */
function fixContent($crawler)
{
    $crawler->filterXPath('//script')->each(function ($node){
        foreach ($node as $item){
            $item->parentNode->removeChild($item);
        }
    });
    return $crawler->html();
}

$spider->on(Spider::EVENT_START_WORKER,function ($event){
    sinaFirst();
    \Workerman\Lib\Timer::add(600,'sinaFirst');
});
//不需要再寻找url
$spider->off(Spider::EVENT_DISCOVER_URL);
$hotApp->run();
