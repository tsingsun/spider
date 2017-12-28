<?php

namespace tsingsun\spider\queue;

interface QueueInterface
{
    /**
     * 加入待处理队列
     * @param string $url
     * @param array $option
     * @return mixed
     */
    public function add($url = '', $option = []);

    /**
     * 将链接加入到已处理队列
     * @param $url
     * @return mixed
     */
    public function queued($url);

    /**
     * 取待处理队列的下一项
     * @return mixed
     */
    public function next();

    /**
     * 待处理队列的数量
     * @return mixed
     */
    public function count();
    /**
     * 已处理队列的数量
     * @return int
     */
    public function queuedCount();

    /**
     * 指定项是否在已处理队列中
     * @param $url
     * @return bool
     */
    public function isQueued($url);

    /**
     * @return void 清空所有队列信息
     */
    public function clean();
}
