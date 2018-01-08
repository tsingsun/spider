<?php

namespace tsingsun\spider\queue;

use tsingsun\spider\RequestItem;

interface QueueInterface
{
    /**
     * 加入待处理队列
     * @param RequestItem $requestItem
     */
    public function add($requestItem);

    /**
     * 将链接加入到已处理队列
     * @param RequestItem $requestItem
     * @return mixed
     */
    public function queued($requestItem);

    /**
     * 取待处理队列的下一项
     * @return RequestItem|null
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
     * @param RequestItem $requestItem
     * @return bool
     */
    public function isQueued($requestItem);

    /**
     * @return void 清空所有队列信息
     */
    public function clean();
}
