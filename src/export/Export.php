<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/12/27
 * Time: 下午6:20
 */

namespace tsingsun\spider\export;


use tsingsun\spider\helper\Util;
use tsingsun\spider\Spider;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\Connection;
use yii\di\Instance;
use yii\helpers\FileHelper;

class Export extends Behavior
{
    public $exportType;
    public $exportFile;
    /**
     * @var string|Connection
     */
    public $exportDb = 'db';
    /** @var string */
    public $exportTable;

    public function init()
    {
        parent::init();
        if($this->exportType == 'db' && !is_object($this->exportDb)){
            $this->exportDb = Instance::ensure($this->exportDb,Connection::className());
        }
    }

    public function events()
    {
        return [
            Spider::EVENT_AFTER_DOWNLOAD_PAGE => 'onAfterDownloadPage',
        ];
    }

    /**
     * @param Event $event
     */
    public function onAfterDownloadPage($event)
    {
        if($data = $event->sender->data){
            switch ($this->exportType) {
                case 'cvs':
                    Util::putFile($this->exportFile, util::formatCsv($data)."\n", FILE_APPEND);
                    break;
                case 'sql':
                    $sql = $this->exportDb->createCommand()->insert($this->exportTable,$data)->getRawSql();
                    Util::putFile($this->exportFile, $sql."\n", FILE_APPEND);
                    break;
                case 'db':
                    $this->exportDb->createCommand()->insert($this->exportTable,$data)->execute();
                    break;
            }
        }

    }


}