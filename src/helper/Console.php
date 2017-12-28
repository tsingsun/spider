<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/20
 * Time: 上午11:13
 */

namespace tsingsun\spider\helper;


use tsingsun\spider\Application;
use tsingsun\spider\helper\Util;
use tsingsun\spider\queue\QueueInterface;
use tsingsun\spider\Spider;

class Console extends \yii\helpers\Console
{
    // 运行面板参数长度
    public static $server_length = 10;
    public static $tasknum_length = 8;
    public static $taskid_length = 8;
    public static $pid_length = 8;
    public static $mem_length = 8;
    public static $urls_length = 15;
    public static $speed_length = 6;

    public static function stdout($string)
    {
        return parent::stdout($string);
    }

    public static function stderr($string)
    {
        return parent::stderr($string);
    }
}