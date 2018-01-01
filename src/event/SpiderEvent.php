<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/29
 * Time: 下午2:39
 */

namespace tsingsun\spider\event;


use tsingsun\spider\RequestItem;
use yii\base\Event;

class SpiderEvent extends Event
{
    /**
     * @var RequestItem
     */
    public $request;
}