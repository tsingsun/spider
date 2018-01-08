<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/29
 * Time: 上午9:19
 */

namespace tsingsun\spider;



use yii\helpers\ArrayHelper;

class SpiderCreator
{
    /**
     * @param array $config
     * @return Application
     */
    public static function create($config = [])
    {
        defined('YII_DEBUG') or define('YII_DEBUG', false);
        defined('YII_ENV') or define('YII_ENV', 'prod');
        require_once(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');

        $app = new \tsingsun\spider\Application($config);
        return $app;

    }
}