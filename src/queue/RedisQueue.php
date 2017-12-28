<?php
namespace tsingsun\spider\queue;

use yii\base\Component;
use yii\di\Instance;
use yii\redis\Connection;

class RedisQueue extends Component implements QueueInterface
{
    public $name = '';
    public $redis = 'redis';

    public $host;
    public $port;
    public $database;

    public $maxQueueSize = 10000;
    public $maxQueuedCount = 0;
    //利用redist str做去重,256M支持近亿条数据
    public $bloomFilter = true;
    /**
     * @var string 队列键
     */
    protected $key = '';
    /** @var string 已爬队列键 */
    protected $queuedKey = '';
    /**
     * breadth,depth
     * @var string
     */
    protected $algorithm = 'depth';
    //bloom filter setting
    public $bfSize = 400000;
    public $bfHashCount = 14;

    public function init()
    {
        parent::init();
        $this->key = $this->name . 'Queue';
        $this->queuedKey = $this->name . 'Queued';
        $this->getInstance()->sadd('spider', $this->name);
    }

    /**
     * @return \Redis
     */
    public function getInstance()
    {
        if (!is_object($this->redis)) {
//            $this->redis = Instance::ensure($this->redis,Connection::className());
            $this->redis = new \Redis();
            $this->redis->connect($this->host, $this->port);
        }
        return $this->redis;
    }

    public function add($url = '', $options = [])
    {
        if ($this->maxQueueSize != 0 && $this->count() >= $this->maxQueueSize) {
            return;
        }

        $queue = serialize([
            'url' => $url,
            'options' => $options,
        ]);

        if ($this->isQueued($queue)) {
            return;
        }

        $this->getInstance()->rPush($this->key, $queue);
    }

    public function next()
    {
        if ($this->algorithm == 'depth') {
            $queue = $this->getInstance()->lpop($this->key);
        } else {
            $queue = $this->getInstance()->rpop($this->key);
        }

        if ($this->isQueued($queue)) {
            return $this->next();
        } else {
            return unserialize($queue);
        }
    }

    public function count()
    {
        return $this->getInstance()->lLen($this->key);
    }

    public function queued($queue)
    {
        if ($this->bloomFilter) {
            $this->bfAdd(md5(serialize($queue)));
        } else {
            $this->getInstance()->sadd($this->queuedKey, serialize($queue));
        }
    }

    public function isQueued($queue)
    {
        if ($this->bloomFilter) {
            return $this->bfHas(md5($queue));
        } else {
            return $this->getInstance()->sismember($this->queuedKey, $queue);
        }
    }

    public function queuedCount()
    {
        if ($this->bloomFilter) {
            return 0;
        } else {
            return $this->getInstance()->sCard($this->queuedKey);
        }
    }

    public function clean()
    {
        $this->getInstance()->del($this->key);
        $this->getInstance()->del($this->queuedKey);
        $this->getInstance()->srem('spider', $this->name);
    }

    protected function bfAdd($item)
    {
        $index = 0;
        $pipe = $this->getInstance()->pipeline();
        while ($index < $this->bfHashCount) {
            $crc = $this->hash($item, $index);
            $pipe->setbit($this->queuedKey, $crc, 1);
            $index++;
        }
        $pipe->exec();
    }

    protected function bfHas($item)
    {
        $index = 0;
        $pipe = $this->getInstance()->pipeline();
        while ($index < $this->bfHashCount) {
            $crc = $this->hash($item, $index);
            $pipe->getbit($this->queuedKey, $crc);
            $index++;
        }
        $result = $pipe->exec();
        return !in_array(0, $result);
    }

    protected function hash($item, $index)
    {
        return abs(crc32(md5('m' . $index . $item))) % $this->bfSize;
    }
}
