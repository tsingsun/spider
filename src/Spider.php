<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/18
 * Time: 下午11:31
 */

namespace tsingsun\spider;


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
    public $urlFilter = [];
    public $interval = 1;
    public $timeout = 5;
    public $userAgent = 'pc';
    /**
     * 爬虫所对应的队列
     * @var QueueInterface
     */
    public $queue = 'queue';
    /**
     * @var array 队列项
     */
    public $current;
    public $url = '';
    public $method = '';
    public $options = [];
    public $page = '';
    /**
     * @var Worker
     */
    public $worker = null;
    protected $timer_id = null;

    /**
     * @var \tsingsun\spider\Application
     */
    private $app;

    public function init()
    {
        $this->app = \Yii::$app;
        $this->daemonize = $this->app->daemonize;
        parent::init();
        if(!$this->name){
            $this->name = \Yii::$app->name;
        }
        $this->on(self::EVENT_START_WORKER, [$this, 'onStartWorker']);
        $this->on(self::EVENT_START_WORKER, [$this, 'onWorkerStop']);
        $this->on(self::EVENT_BEFORE_DOWNLOAD_PAGE, [$this, 'onBeforeDownloadPage']);
        $this->on(self::EVENT_DOWNLOAD_PAGE, [$this, 'onDownloadPage']);
        $this->on(self::EVENT_AFTER_DOWNLOAD_PAGE,[$this,'onAfterDownloadPage']);
        $this->on(self::EVENT_DISCOVER_URL, [$this, 'onDiscoverUrl']);
        $this->on(self::EVENT_AFTER_DISCOVER, [$this, 'onAfterDiscover']);
    }

    public function setApp($app)
    {
        $this->app = $app;
    }

    /**
     * @param Event $event
     */
    public function onStartWorker($event)
    {
        /** @var self $instance */
        $instance = $event->sender;
        $instance->queue()->maxQueueSize = $instance->max;
        $instance->timer_id = $this->app->getWorker()::timer($instance->interval, [$instance, 'crawler']);
    }

    /**
     * 执行爬虫，非守护模式
     */
    public function start()
    {
        if($this->daemonize){
            foreach ((array)$this->seed as $url) {
                if (is_string($url)) {
                    $this->queue()->add($url);
                } elseif (is_array($url)) {
                    $this->queue()->add($url[0], $url[1]);
                }
            }
        }else{
            $this->seed = (array)$this->seed;
            while (count($this->seed)) {
                $this->crawler();
            }
        }
    }

    /**
     * @return QueueInterface
     */
    public function queue()
    {
        if (!is_object($this->queue )) {
            $this->queue = Instance::ensure($this->queue,QueueInterface::class);
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

    public function crawler()
    {
        try {
            $event = new Event();
            $event->sender = $this;
            $this->trigger(self::EVENT_BEFORE_DOWNLOAD_PAGE, $event);
            $this->trigger(self::EVENT_DOWNLOAD_PAGE, $event);
            $this->trigger(self::EVENT_AFTER_DOWNLOAD_PAGE, $event);
            $this->trigger(self::EVENT_DISCOVER_URL, $event);
            $this->trigger(self::EVENT_AFTER_DISCOVER, $event);
        } catch (\Exception $e) {
            Console::stderr($e->getMessage());
            if ($this->daemonize) {
                $this->queue()->add($this->current['url'], $this->current['options']);
            } else {
                $this->seed[] = $this->current;
            }
            if($this->daemonize){
                $this->worker->stop();
            }
//            $this->trigger(self::EVENT_CRAWLER_ERROR);
        }

        $this->current = '';
        $this->url = '';
        $this->method = '';
        $this->page = '';
        $this->options = [];
    }

    public function onWorkerStop($worker)
    {

    }

    /**
     * @param Event $event
     * @throws \Exception
     */
    public function onBeforeDownloadPage($event)
    {
        if ($this->daemonize) {
            if ($this->max > 0 && $this->queue()->queuedCount() >= $this->max) {
                $msg = "Download to the upper limit, worker {$this->name} stop downloading.";
                Console::stdout($msg);
                $this->app->getWorker()::timerDel($this->timer_id);
                throw new \Exception($msg);
            }

            $this->current = $queue = $this->queue()->next();
        } else {
            $queue = array_shift($this->seed);
        }

        if (is_null($queue) || !$queue) {
            sleep(30);
            throw new SpiderException('empty queue',SpiderException::EMPTY_QUEUE);
        }

        if (!is_array($queue)) {
            $this->current = $queue = [
                'url' => $queue,
                'options' => [],
            ];
        }

        $options = array_merge([
            'headers' => [],
            'reserve' => false,
            'timeout' => $this->timeout,
        ], (array)$queue['options']);

        if ($this->daemonize && $options['reserve'] && $this->queue()->isQueued(serialize($queue))) {
            $event->handled = true;
            return;
        }

        $this->url = $queue['url'];
        $this->method = isset($options['method']) ? $options['method'] : 'GET';
        $this->options = $options;
        if (!isset($this->options['headers']['User-Agent'])) {
            $this->options['headers']['User-Agent'] = Util::randUserAgent($this->userAgent);
        }
    }


    /**
     * @param Event $event
     * @throws
     */
    public function onDownloadPage($event)
    {
        $response = $this->app->getClient()->request($this->method, $this->url, $this->options);
        $this->page = $response->getBody();
        if ($this->page) {
            $worker_id = isset($this->id) ? $this->worker->id : '';
            Console::stdout("worker {$worker_id} download {$this->url} success.\n");
        } else {
            throw new SpiderException("the {$this->url} return empty body");
        }
    }

    /**
     * 下载页面后，可以在此进行解析数据
     * @param Event $event
     */
    public function onAfterDownloadPage($event)
    {

    }

    /**
     * @param Event $event ;
     */
    public function onDiscoverUrl($event)
    {
        $countUrlFilter = count($this->urlFilter);
        if ($countUrlFilter === 1 && !$this->urlFilter[0]) {
            $event->handled = true;
            return;
        }

        $urls = Util::getUrlByHtml($this->page, $this->url);

        if ($countUrlFilter > 0) {
            foreach ($urls as $url) {
                foreach ($this->urlFilter as $urlPattern) {
                    if (preg_match($urlPattern, $url)) {
                        $this->queue()->add($url);
                    }
                }
            }
        } else {
            foreach ($urls as $url) {
                $this->queue()->add($url);
            }
        }
    }

    /**
     * @param Event $event
     */
    public function onAfterDiscover($event)
    {
        if($this->options['reserve'] == false) {
            $this->queue()->queued($this->current);
        }
    }

}