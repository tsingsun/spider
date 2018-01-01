<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/29
 * Time: 上午9:19
 */

namespace tsingsun\spider;



class SpiderCreator
{
    /**
     * @param array $config
     * @return Application
     */
    public static function create($config = [])
    {
        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'test');
        require_once(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

        $app = new \tsingsun\spider\Application($config);
        return $app;

    }
}