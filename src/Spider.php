<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/18
 * Time: 下午11:31
 */

namespace tsingsun\spider;


use Goutte\Client;
use GuzzleHttp\Psr7\Stream;
use Symfony\Component\DomCrawler\Crawler;
use tsingsun\spider\event\SpiderEvent;
use tsingsun\spider\exception\SpiderException;
use tsingsun\spider\helper\Console;
use tsingsun\spider\helper\Util;
use tsingsun\spider\queue\QueueInterface;
use Workerman\Worker;
use yii\base\Component;
use yii\base\Event;
use yii\di\Instance;

class Spider extends Component
{
    //守护进程workder启动时
    const EVENT_START_WORKER = 'startWorker';
    //守护进程workder关闭时
    const EVENT_WORKER_STOP = 'workerStop';
    //页面下载前
    const EVENT_BEFORE_DOWNLOAD_PAGE = 'beforeDownloadPage';
    //页面下载
    const EVENT_DOWNLOAD_PAGE = 'downloadPage';
    //页面下载前
    const EVENT_AFTER_DOWNLOAD_PAGE = 'afterDownloadPage';
    //发现新的URL
    const EVENT_DISCOVER_URL = 'discoverUrl';
    //发现新的URL
    const EVENT_AFTER_DISCOVER = 'afterDiscoverUrl';
    //爬取过程中错误
    const EVENT_CRAWLER_ERROR = 'crawlerError';
    //基础设置
    public $name = null;
    public $max = 0;
    public $seed = [];
    public $daemonize = false;
    public $taskNum = 1;
    //url过滤规则
    public $urlFilter = [];
    public $interval = 1;
    public $timeout = 30;
    public $userAgent = 'pc';
    /**
     * 爬虫所对应的队列
     * @var QueueInterface
     */
    public $queue = 'queue';
    /**
     * @var RequestItem 队列项
     */
    public $current;
    /**
     * @var Crawler
     */
    public $crawler;
    /**
     * @var Worker
     */
    public $worker = null;
    protected $timer_id = null;

    /**
     * @var \tsingsun\spider\Application
     */
    private $app;

    public function __construct(array $config = [])
    {
        $this->on(self::EVENT_START_WORKER, [$this, 'onStartWorker']);
        $this->on(self::EVENT_START_WORKER, [$this, 'onWorkerStop']);
        $this->on(self::EVENT_BEFORE_DOWNLOAD_PAGE, [$this, 'onBeforeDownloadPage']);
        $this->on(self::EVENT_DOWNLOAD_PAGE, [$this, 'onDownloadPage']);
        $this->on(self::EVENT_AFTER_DOWNLOAD_PAGE, [$this, 'onAfterDownloadPage']);
        $this->on(self::EVENT_DISCOVER_URL, [$this, 'onDiscoverUrl']);
        $this->on(self::EVENT_AFTER_DISCOVER, [$this, 'onAfterDiscover']);
        parent::__construct($config);
    }

    public function init()
    {
        $this->app = \Yii::$app;
        $this->daemonize = $this->app->daemonize;
        parent::init();
        if (!$this->name) {
            $this->name = \Yii::$app->name;
        }
    }

    public function setApp($app)
    {
        $this->app = $app;
    }

    /**
     * @param Event $event
     * @internal
     */
    public function onStartWorker($event)
    {
        /** @var self $instance */
        $instance = $event->sender;
        $instance->queue()->maxQueueSize = $instance->max;
        $instance->timer_id = $this->app->getWorker()::timer($instance->interval, [$instance, 'crawler']);
    }

    /**
     * 执行爬虫
     */
    public function start()
    {
        foreach ((array)$this->seed as $url) {
            if (is_string($url)) {
                $this->queue()->add(new RequestItem(['url' => $url]));
            } elseif (is_array($url)) {
                $this->queue()->add(new RequestItem($url));
            }
        }
        if (!$this->daemonize) {
            while ($this->queue()->count()) {
                $this->crawler();
            }
        }
    }

    /**
     * @return QueueInterface
     */
    public function queue()
    {
        if (!is_object($this->queue)) {
            $this->queue = Instance::ensure($this->queue, QueueInterface::class);
//            $config = \Yii::$app->getComponents(true)['queue'];
//            $class = $config['class'];
//            if (!isset($config['name'])) {
//                $config['name'] = $this->name;
//            }
//            unset($config['class']);
//            $this->queues = \Yii::createObject($class, [$config]);
        }
        return $this->queue;
    }

    /**
     * @param RequestItem $request 请求内容,包含最基本的method,url,options
     */
    public function crawler($request = null)
    {
        try {
            $event = new SpiderEvent();
            $event->sender = $this;
            if ($request !== null) {
                $event->request = $request;
            }
            $this->trigger(self::EVENT_BEFORE_DOWNLOAD_PAGE, $event);
            $this->trigger(self::EVENT_DOWNLOAD_PAGE, $event);
            $this->trigger(self::EVENT_AFTER_DOWNLOAD_PAGE, $event);
            $this->trigger(self::EVENT_DISCOVER_URL, $event);
            $this->trigger(self::EVENT_AFTER_DISCOVER, $event);
        } catch (SpiderException $se) {
            Console::stderr($se->getMessage() . "\n");
            \Yii::$app->getErrorHandler()->logException($se);
            if ($this->current) {
                $this->queue()->add($this->current);
            }
        } catch (\Exception $e) {
            Console::stderr($e->getMessage() . "\n");
            \Yii::$app->getErrorHandler()->logException($e);
            if ($this->current) {
                $this->queue()->add($this->current);
            }
            if ($this->daemonize) {
                $this->worker->stop();
            }
//            $this->trigger(self::EVENT_CRAWLER_ERROR);
        }

        $this->current = null;
        $this->crawler = null;
    }

    /**
     * @param $worker
     * @internal
     */
    public function onWorkerStop($worker)
    {

    }

    /**
     * @param SpiderEvent $event
     * @throws \Exception
     * @internal
     */
    public function onBeforeDownloadPage($event)
    {
        if ($event->request) {
            $queue = $event->request;
        } else {
            if ($this->max > 0 && $this->queue()->queuedCount() >= $this->max) {
                $msg = "Download to the upper limit, worker {$this->name} stop downloading.";
                Console::stdout($msg);
                if ($this->daemonize) {
                    $this->app->getWorker()::timerDel($this->timer_id);
                }
                throw new \Exception($msg);
            }
            $queue = $this->queue()->next();
        }

        if (is_null($queue) || !$queue) {
            sleep(30);
            throw new SpiderException('empty queue,sleep 30s', SpiderException::EMPTY_QUEUE);
        }

        //the queue item must array
        $this->current = $queue;

        $options = array_merge([
            'headers' => [],
            'reserve' => false,
            'timeout' => $this->timeout,
        ], (array)$queue['options']);

        if ($this->daemonize && $options['reserve'] && $this->queue()->isQueued($this->current)) {
            $event->handled = true;
            return;
        }
        $this->current->options = $options;
        if($options['method'] ?? false){
            $this->current->method = $options['method'];
        }
        if (!isset($this->current->options['headers']['User-Agent'])) {
            $this->current->options['headers']['User-Agent'] = Util::randUserAgent($this->userAgent);
        }

    }


    /**
     * @param SpiderEvent $event
     * @throws
     * @internal
     */
    public function onDownloadPage($event)
    {
        $guzzleClient = $this->app->getClient();
        $client = new Client();
        $client->setClient($guzzleClient);
        $this->crawler = $client->request($this->current->method, $this->current->url,[],[],$this->current->options);
        if ($this->crawler) {
            $worker_id = $this->worker->id;
            Console::stdout("worker {$worker_id} download {$this->current->url} success.\n");
        } else {
            throw new SpiderException("the {$this->current->url} return empty body");
        }
    }

    /**
     * 下载页面后，可以在此进行解析数据
     * @param SpiderEvent $event
     * @internal
     */
    public function onAfterDownloadPage($event)
    {

    }

    /**
     * @param SpiderEvent $event ;
     * @internal
     */
    public function onDiscoverUrl($event)
    {
        $countUrlFilter = count($this->urlFilter);
        if ($countUrlFilter === 1 && !$this->urlFilter[0]) {
            $event->handled = true;
            return;
        }
//        $urls = $this->crawler->filterXPath('//a[@href]')->each(function ($node){
//            return $node->text();
//        });
        $urls = Util::getUrlByHtml($this->crawler->html(), $this->current->url);

        if ($countUrlFilter > 0) {
            foreach ($urls as $url) {
                foreach ($this->urlFilter as $urlPattern) {
                    if (preg_match($urlPattern, $url)) {
                        $this->queue()->add(new RequestItem(['url' => $url]));
                    }
                }
            }
        } else {
            foreach ($urls as $url) {
                $this->queue()->add(new RequestItem(['url' => $url]));
            }
        }
    }

    /**
     * @param SpiderEvent $event
     * @internal
     */
    public function onAfterDiscover($event)
    {
        if ($this->current->options['reserve'] == false) {
            $this->queue()->queued($this->current);
        }
    }

}