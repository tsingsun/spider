<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/21
 * Time: 下午3:03
 */

namespace tsingsun\spider\queue;
use yii\base\Component;

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

    public function add($url = '',$options = [])
    {
        if ($this->maxQueueSize != 0 && $this->count() >= $this->maxQueueSize) {
            return;
        }

        $queue = [
            'url' => $url,
            'options' => $options,
        ];

        if ($this->isQueued($queue)) {
            return;
        }
        array_push($this->globalData[$this->key],$url);
    }

    public function queued($queue)
    {
        array_push($this->globalData[$this->queuedKey], serialize($queue));
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
        } else {
            return $queue;
        }
    }

    public function count()
    {
        return count($this->globalData[$this->key]);
    }

    public function queuedCount()
    {
        return count($this->globalData[$this->queuedKey]);
    }

    public function isQueued($queue)
    {
        return in_array(serialize($queue), $this->globalData[$this->queuedKey]);
    }

    public function clean()
    {
        $this->globalData = null;
    }

}