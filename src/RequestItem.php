<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/29
 * Time: 下午3:10
 */

namespace tsingsun\spider;

use yii\base\BaseObject;

/**
 * 爬虫的请求项,包含了基本的http request信息
 * @package tsingsun\spider
 */
class RequestItem implements \ArrayAccess,\JsonSerializable
{
    /**
     * @var string http method,get/post and so on.
     */
    public $method = 'GET';
    /**
     * @var string the uri info
     */
    public $url;
    /**
     * @var array http client options,like GuzzleHttp/Client,curl
     */
    public $options;

    public function __construct($config = [])
    {
        foreach ($config as $key=>$item){
            $this->$key = $item;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    public function offsetUnset($offset)
    {
        $this->$offset = null;
    }

    public function jsonSerialize()
    {
        $result = [
            'url' => $this->url,
        ];
        if(!empty($this->options)){
            $result['options'] = $this->options;
        }
        if($this->method){
            $result['method'] = $this->method;
        }
        return $result;
    }


}