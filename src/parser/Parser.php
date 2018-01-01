<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/27
 * Time: 上午9:58
 */

namespace tsingsun\spider\parser;


use Symfony\Component\DomCrawler\Crawler;
use tsingsun\spider\Spider;
use yii\base\Behavior;
use yii\base\Event;
use yii\helpers\StringHelper;

/**
 * 通过配置对下载的网页进行解析
 *
 * 配置为树型结构
 *  - node 表示一个配置节点
 *    - key 字段名
 *      - required 是否必须,默认false,如果字段无数据,是(false)否(true)保留整组数据
 *      - selector 选择器表示
 *      - selectorType 选择器类型,支持xpath,css
 *      - repeated 是否为数组值
 *      - children 子节点数组,是node[]类型
 *      - sourceType 表示field的值通过数据源方式获取
 *      - attached_url 默认当前页面,
 *      - default 当前页面默认值,支持:url
 * @package tsingsun\spider\parser
 */
class Parser extends Behavior
{
    /** @var array 字段配置 */
    public $fields;
    /** @var array 解析后的数据 */
    public $data;
    //内容页规则
    public $contentUrlFilter;
    /** @var Crawler */
    private $crawler;

    public function events()
    {
        return [
            Spider::EVENT_AFTER_DOWNLOAD_PAGE => 'onAfterDownloadPage',
            Spider::EVENT_AFTER_DISCOVER => 'onAfterDiscover',
        ];
    }

    /**
     * @param Event $event
     */
    public function onAfterDownloadPage($event)
    {
        /** @var Spider $spider */
        $spider = $event->sender;
        if(preg_match($this->contentUrlFilter,$spider->current->url)){
            $this->crawler = new Crawler();
            $this->crawler->addHtmlContent($spider->page);
            $this->data = $this->getData($this->fields);
        }
    }

    /**
     * @param Event $event
     */
    public function onAfterDiscover($event)
    {
        $this->data = null;
        $this->crawler = null;
    }

    public function getData($fields)
    {
        $data = [];
        foreach ($fields as $key => $field) {
            // 当前field抽取到的内容是否是有多项
            $repeated = isset($field['repeated']) && $field['repeated'] ? true : false;
            // 当前field抽取到的内容是否必须有值
            $required = isset($field['required']) && $field['required'] ? true : false;

            if(isset($field['default'])){
                $data[$key] = $this->parseDefault($field['default']);
                continue;
            }

            if(isset($field['sourceType']) && $field['sourceType']=='attached_url'){
                // 取出上个field的内容作为连接, 内容分页是不进队列直接下载网页的
                if (!empty($fields[$field['attached_url']])) {
                    //todo 对指向内容进行爬取
                }
            }

            if (isset($field['selector'])) {
                $selectorType = $field['selectorType'] ?? 'xpath';
                if ($selectorType == 'xpath') {
                    $subCrawler = $this->crawler->filterXPath($field['selector']);
                } else {
                    $subCrawler = $this->crawler->filter($field['selector']);
                }
                if (isset($field['callback'])) {
                    $data[$key] = call_user_func($field['callback'], $subCrawler);
                } else {
                    $value = $subCrawler->each(function (Crawler $node,$i){
                          return $node->text();
                    });
                    if($repeated){
                        $data[$key] = $value;
                    }else{
                        $data[$key] = $value[0] ?? '';
                    }
                }
            }
            if (isset($field['children'])) {
                $data[$key] = $this->getData($field['children']);
            }
        }
        return $data;
    }

    private function parseUrl($data,$url)
    {
        $patten = '|\{\w\}|';

        return preg_replace_callback($patten,function ($matches)use($data){
            $key = substr($matches[0],1,strlen($matches[0])-1);
            return $data[$key];
        },$url);
    }

    private function parseDefault($key)
    {
        $value = null;
        switch ($key){
            case 'url':
                $value = $this->owner->current->url;
                break;
            default:
                break;
        }
        return $value;
    }
}