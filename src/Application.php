<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/18
 * Time: 下午11:31
 */

namespace tsingsun\spider;


use GuzzleHttp\Client;
use tsingsun\spider\worker\Workerman;
use yii\base\Exception;
use yii\base\InvalidRouteException;
use yii\console\Response;
use yii\helpers\ArrayHelper;
use yii\redis\Connection;

class Application extends \yii\base\Application
{
    /**
     * 版本号
     */
    const VERSION = '1.0.0';
    public $name = 'spider';
    /**
     * @var string 日志路径
     */
    public $logFile;
    /**
     * @var bool 是否守护模式
     */
    public $daemonize = false;

    private $defautLog = [
        'id' => 'spider',
        'bootstrap' => ['log'],
    ];

    public function __construct(array $config = [])
    {
        $config = ArrayHelper::merge($this->defautLog, $config);
        if (!isset($config['components']['log'])) {
            $this->logFile = $config['logPath'] ?? "@runtime/log/".$config['id'] . '.log';
            $config['components']['log'] = [
                'flushInterval'=>1,
                'targets' => [
                    [
                        'class' => 'yii\log\FileTarget',
                        'prefix' => function ($message) {
                            return null;
                        },
                        'levels'=>['info','error','warning'],
                        'logVars'=>[],
                        'logFile' => $this->logFile,
                    ]
                ],
            ];
        }
        parent::__construct($config);
    }

    public function init()
    {
        parent::init();
    }



    /**
     * @param \yii\console\Request $request
     * @return mixed|\yii\base\Response|Response|\yii\web\Response
     * @throws InvalidRouteException
     */
    public function handleRequest($request)
    {
        global $argv;
        $request->setParams($argv);
        list($route, $params) = $request->resolve();

        $this->requestedRoute = $route;
        $command = $this->parseCommand($params);
        //fix the argv when in xdebug
        $argv = [$route,$command];
        $spider =$this->getSpider();

        $this->runCommand($spider, $command);
        if($this->daemonize){
            $this->getWorker()->start();
        }
        $response = $this->getResponse();
        $response->exitStatus = true;
        return $response;
    }

    /**
     * @inheritdoc
     */
    public function coreComponents()
    {
        return array_merge(parent::coreComponents(), [
            'request' => ['class' => 'yii\console\Request'],
            'response' => ['class' => 'yii\console\Response'],
            'errorHandler' => ['class' => 'yii\console\ErrorHandler'],
            'spider' => [
                'class' => 'tsingsun\spider\Spider'
            ],
            'worker'=>['class'=> 'tsingsun\spider\worker\Workerman'],
            'queue'=>['class'=>'tsingsun\spider\queue\ArrayQueue'],
            'client'=>['class'=>'GuzzleHttp\Client'],
        ]);
    }

    public function options()
    {
        return ['daemonize'];
    }

    public function optionAliases()
    {
        return ['d' => 'daemonize'];
    }

    public function parseCommand($params)
    {
        $options = $this->options();
        if (isset($params['_aliases'])) {
            $optionAliases = $this->optionAliases();
            foreach ($params['_aliases'] as $name => $value) {
                if (array_key_exists($name, $optionAliases)) {
                    $params[$optionAliases[$name]] = $value;
                } else {
                    throw new Exception(Yii::t('yii', 'Unknown alias: -{name}', ['name' => $name]));
                }
            }
            unset($params['_aliases']);
        }
        foreach ($params as $name => $value) {
            if (in_array($name, $options, true)) {
                $default = $this->$name;
                if (is_array($default)) {
                    $this->$name = preg_split('/\s*,\s*(?![^()]*\))/', $value);
                } elseif ($default !== null) {
                    settype($value, gettype($default));
                    $this->$name = $value;
                } else {
                    $this->$name = $value;
                }
                unset($params[$name]);
            } elseif (!is_int($name)) {
                throw new Exception(Yii::t('yii', 'Unknown option: --{name}', ['name' => $name]));
            }
        }
        return $params[0] ?? 'start';
    }

    /**
     * 执行脚本命令,只在linux下
     * @param Spider $spider
     * @param $command
     */
    public function runCommand($spider, $command)
    {
        switch ($command) {
            case 'start':
                echo "{$this->name} spider is starting...\n";

//                fclose(STDOUT);
//                $STDOUT = fopen($this->logFile, "a");
                $spider->start();
                break;
            case 'clean':
                $spider->queue()->clean();
                unlink($this->logFile);
                die();
                break;
            case 'stop':
                break;
            default:
                break;
        }
    }

    /**
     * 针对守护模式的检查
     */
    public function check()
    {
        $error = false;
        $text = '';
        $version_ok = $pcntl_loaded = $posix_loaded = true;
        if (!version_compare(phpversion(), "5.3.3", ">=")) {
            $text .= "PHP Version >= 5.3.3                 \033[31;40m [fail] \033[0m\n";
            $error = true;
        }

        if (!in_array("pcntl", get_loaded_extensions())) {
            $text .= "Extension posix check                \033[31;40m [fail] \033[0m\n";
            $error = true;
        }

        if (!in_array("posix", get_loaded_extensions())) {
            $text .= "Extension posix check                \033[31;40m [fail] \033[0m\n";
            $error = true;
        }

        $check_func_map = array(
            "stream_socket_server",
            "stream_socket_client",
            "pcntl_signal_dispatch",
        );

        if ($disable_func_string = ini_get("disable_functions")) {
            $disable_func_map = array_flip(explode(",", $disable_func_string));
        }

        foreach ($check_func_map as $func) {
            if (isset($disable_func_map[$func])) {
                $text .= "\033[31;40mFunction " . implode(', ', $check_func_map) . "may be disabled. Please check disable_functions in php.ini\033[0m\n";
                $error = true;
                break;
            }
        }

        if ($error) {
            echo $text;
            exit;
        }
    }

    /**
     * @return null|object|\yii\console\Request
     */
    public function getRequest()
    {
        return $this->get('request');
    }

    /**
     * @return Spider
     */
    public function getSpider()
    {
        return $this->get('spider');
    }

    /**
     * @return Connection
     */
    public function getRedis()
    {
        return $this->get('redis');
    }

    /**
     * @return Workerman
     */
    public function getWorker()
    {
        return $this->get('worker');
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->get('client');
    }
}