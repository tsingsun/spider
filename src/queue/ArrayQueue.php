<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/21
 * Time: 下午3:03
 */

namespace tsingsun\spider\queue;
use tsingsun\spider\RequestItem;
use yii\base\Component;
use yii\db\Exception;

/**
 * 数据队列,方便于开发与调试,仅限于非守护模式
 */
class ArrayQueue extends Component implements QueueInterface
{
    /** @var string 组件名称 */
    public $name = '';
    /** @var int 队列长度 */
    public $maxQueueSize = 10000;
    public $maxQueuedCount = 0;
    public $bloomFilter = true;

    private $globalData = null;
    protected $key = '';
    protected $queuedKey = '';

    public $algorithm = 'depth';

    public function init()
    {
        parent::init();
        $this->key = $this->name . 'Queue';
        $this->queuedKey = $this->name . 'Queued';

        if (isset($config['algorithm'])) {
            $this->algorithm = $this->algorithm != 'breadth' ? 'depth' : 'breadth';
        }

        $this->globalData[$this->key] = [];
        $this->globalData[$this->queuedKey] = [];
        $this->globalData['spider'] = [];
    }

    public function add($requestItem)
    {
        if(!$requestItem){
            throw new Exception('queue item is not empty');
        }

        if ($this->maxQueueSize != 0 && $this->count() >= $this->maxQueueSize) {
            return;
        }

        if ($this->isQueued($requestItem)) {
            return;
        }

//        $queue = json_encode($requestItem);

        array_push($this->globalData[$this->key],$requestItem);
    }

    public function queued($queue)
    {
        array_push($this->globalData[$this->queuedKey], $queue->url);
    }

    public function next()
    {
        if ($this->algorithm == 'depth') {
            $queue = array_shift($this->globalData[$this->key]);
        } else {
            $queue = array_pop($this->globalData[$this->key]);
        }

        if ($this->isQueued($queue)) {
            return $this->next();
        } elseif($queue) {
            return $queue;
        }
        return null;
    }

    public function count()
    {
        return count($this->globalData[$this->key]);
    }

    public function queuedCount()
    {
        return count($this->globalData[$this->queuedKey]);
    }

    public function isQueued($requestItem)
    {
        return in_array($requestItem->url, $this->globalData[$this->queuedKey]);
    }

    public function clean()
    {
        $this->globalData = null;
    }

}