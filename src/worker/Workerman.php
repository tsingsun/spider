<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/20
 * Time: 上午10:48
 */

namespace tsingsun\spider\worker;


use tsingsun\spider\Application;
use tsingsun\spider\Spider;
use Workerman\Lib\Timer;
use Workerman\Worker;
use yii\base\Event;

class Workerman
{

    public static function timer($interval, $callback, $args = [], $persistent = true)
    {
        return Timer::add($interval, $callback, $args, $persistent);
    }

    public static function timerDel($time_id)
    {
        Timer::del($time_id);
    }

    public function start()
    {
        $worker = new Worker();
        /** @var Application $app */
        $app = \Yii::$app;
        $spider = $app->getSpider();
        $spider->daemonize = true;

        $worker->count = $spider->taskNum;
        $worker->name = $spider->name;
        $worker->onWorkerStart = [$this,'onWorkerStart'];
        $worker->onWorkerStop = [$this,'onWorkerStop'];

        $spider->worker = $worker;
        Worker::$daemonize = !YII_DEBUG;

        Worker::$stdoutFile = $app->logFile;

        Worker::runAll();
    }

    public function onWorkerStart($worker)
    {
        /** @var Spider $spider */
        $spider = \Yii::$app->getSpider();
        $spider->trigger(Spider::EVENT_START_WORKER);
    }

    public function onWorkerStop($worker)
    {
        /** @var Spider $spider */
        $spider = \Yii::$app->getSpider();
        Timer::delAll();
        $spider->trigger(Spider::EVENT_WORKER_STOP);
        \Yii::getLogger()->flush(true);
        exit;
    }
}