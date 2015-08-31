<?php

//error_reporting(E_ALL);
//ini_set('display_errors',1);

include '../EventModel.class.php';
include '../EventBase.class.php';

define("ACT_PATH", dirname(__FILE__));
define('TPL_PATH', ACT_PATH."/tpl/");    

class Event extends EventBase
{
    private $comicId = 529460;

    public function __construct()
    {
        parent::__construct();
        $this->View = new View();
    }
    
    public function index()
    {
        //页面级缓存
        $this->tpsEventConf($eventType, $questionNum);
        
        /*$cacheHtml = $this->readCacheHtml($this->comicId, $eventType, $questionNum);
        if (!empty($cacheHtml))
        {
            echo $cacheHtml;
            exit;
        }*/

        //枪神纪活动
        $this->tpsEventData($eventType);
        
        //人气值、收藏数和月票数
        $comicInfoArr = $this->getComicData($this->comicId, "pgv_count, coll_count");
        //月票
        $monthTotal = $this->getMonthTicket();

        //枪神纪作品章节列表
        $tpsLastArr = array();
        $tpsChapterListArr = $this->getChapterList($this->comicId);
        if (!empty($tpsChapterListArr))
        {
            $tpsLastArr = $tpsChapterListArr[sizeof($tpsChapterListArr) - 1];
        }
        
        $total = sizeof($tpsChapterListArr);
        $pageSize = intval(ceil($total/20));

        //最新20话
        $this->View->assign("tps20ChaptersArr", $total > 20 ? array_slice($tpsChapterListArr, $total - 20) : $tpsChapterListArr);
        $this->View->assign("tpsChapterListArr", $tpsChapterListArr);
        $this->View->assign("tpsLastArr", $tpsLastArr);
        $this->View->assign("comicInfoArr", $comicInfoArr);
        $this->View->assign("monthTotal", $monthTotal);
        $this->View->assign("pageSize", $pageSize);
        $this->View->assign("total", $total);

        $html = $this->View->fetch('index.shtml');

        //$this->writeCacheHtml($this->comicId, $eventType, $questionNum, $html);

        echo $html;exit;
    }
    
    private function getMonthTicket()
    {
        $monthTotal = 0;
        $statsMonth = date('Ym');
        $monthTicketModel = new MonthTicketModel();

        $monthTicketInfo = $monthTicketModel->getComicMonTicByMonById($this->comicId, $statsMonth);
        if (!empty($monthTicketInfo))
        {
            $monthTotal = isset($monthTicketInfo[$this->comicId]['month_total']) ? $monthTicketInfo[$this->comicId]['month_total'] : 0;
            $monthTotal =  number_format($monthTotal);
        }
        
        return $monthTotal;
    }

    private function tpsEventConf(&$eventType, &$questionNum)
    {
        $eventType = 0;
        $questionNum = 0;
        $tpsEventTypeKey = "tps_event_type";
        $tpsChooseEventConfKey = "tps_choose_event_conf";
        $tpsNewEventConfKey = "tps_new_event_conf";
        
        $keyListArr = array($tpsEventTypeKey, $tpsChooseEventConfKey, $tpsNewEventConfKey);
        //$tempArr = ServiceHelper::GetValueListByKeyList($keyListArr);
        $tempArr = ServiceHelper::GetValueListByRedisKeyList($keyListArr);
        
        $tpsEventTypeArr = !empty($tempArr) ? $tempArr[$tpsEventTypeKey] : array();
        
        if (!empty($tpsEventTypeArr))
        {
            foreach ($tpsEventTypeArr as $type)
            {
                if ($type["isOnline"] == 1)
                {
                    $eventType = $type["type"];
                    break;
                }
            }

            if ($eventType == 1)
            {
                $questionNum = $tempArr[$tpsChooseEventConfKey][0]["id"];
            }
            else if ($eventType == 2)
            {
                $questionNum = $tempArr[$tpsNewEventConfKey][0]["id"];
            }
        }
    }

    //枪神纪活动数据
    private function tpsEventData($eventType)
    {
        //枪神纪最新章节、枪神纪轮播广告
        $tpsChooseEventConfKey = "tps_choose_event_conf";
        $tpsEventQuestionKey = "tps_event_question";
        $tpsEventRuleKey = "tps_event_rule";
        $tpsEventAdsKey = "tps_event_advertisements";
        $tpsNewEventRuleKey = "tps_new_event_rule";
        $tpsNewEventConfKey = "tps_new_event_conf";
        $tpsNewEventAdsPicKey = "tps_new_event_ads_pic";
        
        $keyListArr = array($tpsChooseEventConfKey, $tpsEventQuestionKey, $tpsEventRuleKey, $tpsNewEventRuleKey, $tpsEventAdsKey, $tpsNewEventConfKey, $tpsNewEventAdsPicKey);
        //$tempArr = ServiceHelper::GetValueListByKeyList($keyListArr);
        $tempArr = ServiceHelper::GetValueListByRedisKeyList($keyListArr);

        if (empty($eventType))
        {
            $this->tpsEventConf($eventType, $questionNum);
        }
        
        //1.选择题活动 2.拉新活动
        if ($eventType == 1)
        {
            $tpsChooseEventConfArr = !empty($tempArr) ? $tempArr[$tpsChooseEventConfKey] : array();
            $tpsEventQuestionArr = !empty($tempArr) ? $tempArr[$tpsEventQuestionKey] : array();
            $tpsEventRuleArr = !empty($tempArr) ? $tempArr[$tpsEventRuleKey] : array();
            $this->View->assign("tpsChooseEventConfArr", $tpsChooseEventConfArr);
            $this->View->assign("tpsEventQuestionArr", $tpsEventQuestionArr);
            $this->View->assign("tpsEventRuleArr", $tpsEventRuleArr);
        }
        else if ($eventType == 2)
        {
            $tpsNewEventRuleArr = !empty($tempArr) ? $tempArr[$tpsNewEventRuleKey] : array();
            $tpsNewEventConfArr = !empty($tempArr) ? $tempArr[$tpsNewEventConfKey] : array();
            $tpsNewEventAdsPicArr = !empty($tempArr) ? $tempArr[$tpsNewEventAdsPicKey] : array();
            $this->View->assign("tpsNewEventRuleArr", $tpsNewEventRuleArr);
            $this->View->assign("tpsNewEventConfArr", $tpsNewEventConfArr);
            $this->View->assign("tpsNewEventAdsPicArr", $tpsNewEventAdsPicArr);
        }
        
        $tpsEventAdsArr = !empty($tempArr) ? $tempArr[$tpsEventAdsKey] : array();
        $this->View->assign("eventType", $eventType);
        $this->View->assign("tpsEventAdsArr", $tpsEventAdsArr);
    }

    //返回活动的配置数据
    /*private function getEventInfofromConf()
    {
        //枪神纪最新章节、枪神纪轮播广告
        $tpsEventTypeKey = "tps_event_type";
        $tpsChooseEventConfKey = "tps_choose_event_conf";
        $tpsNewEventConfKey = "tps_new_event_conf";
        
        $keyListArr = array($tpsEventTypeKey, $tpsChooseEventConfKey, $tpsNewEventConfKey);
        //$tempArr = ServiceHelper::GetValueListByKeyList($keyListArr);
        $tempArr = ServiceHelper::GetValueListByRedisKeyList($keyListArr);

        //枪神纪活动类型(拉新，选择题)
        $tpsEventTypeArr = !empty($tempArr[$tpsEventTypeKey]) ? $tempArr[$tpsEventTypeKey] : array();

        $EventConfArr = array();
        if (!empty($tpsEventTypeArr))
        {
            $eventType = 0;
            foreach ($tpsEventTypeArr as $type)
            {
                if ($type["isOnline"] == 1)
                {
                    $eventType = $type["type"];
                    break;
                }
            }

            if ($eventType == 1)
            {
                $EventConfArr = !empty($tempArr[$tpsChooseEventConfKey]) ? $tempArr[$tpsChooseEventConfKey] : array();
            }
            else if ($eventType == 2)
            {
                $EventConfArr = !empty($tempArr[$tpsNewEventConfKey]) ? $tempArr[$tpsNewEventConfKey] : array();
            }
            
            $EventConfArr[0]["eventType"] = $eventType;
        }

        return $EventConfArr;
    }*/

    private function readCacheHtml($comicId, $eventType, $questionNum)
    {
        if (!empty($eventType) && !empty($questionNum))
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd') . "/tps_{$comicId}_{$eventType}_{$questionNum}.html";
        }
        else
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd') . "/tps_{$comicId}.html";
        }

        if (file_exists($file))
        {
            $now = time();
            $mtime = filemtime($file);

            if ($mtime + 3600 > $now)
            {
                //读取缓存数据
                return file_get_contents($file);
            }
        }
        return false;
    }
        
    private function writeCacheHtml($comicId, $eventType, $questionNum, $html)
    {
        if (!empty($eventType) && !empty($questionNum))
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd') . "/tps_{$comicId}_{$eventType}_{$questionNum}.html";
        }
        else
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd') . "/tps_{$comicId}.html";
        }
        
        return file_put_contents($file, $html);
    }
    
    //人气值和收藏数
    private function getComicData($comicId, $filed)
    {
        $comicModel = new ComicModel();
        $comicInfoArr = $comicModel->getComicInfoById($filed, $comicId);
        
        if (!empty($comicInfoArr) && isset($comicInfoArr[0]["RowCache"]))
        {
            unset($comicInfoArr[0]["RowCache"]);
            $comicInfoArr[0]["pgv_count"] = number_format($comicInfoArr[0]["pgv_count"]);
            $comicInfoArr[0]["coll_count"] = number_format($comicInfoArr[0]["coll_count"]);
        }
        else
        {
            $comicInfoArr[0]["pgv_count"] = 0;
            $comicInfoArr[0]["coll_count"] = 0;
        }
        
        return $comicInfoArr;
    }

    //获取活动的章节列表
    private function getChapterList($comicId, $num = 0)
    {
        $chapterListArr = array();

        $chapterModel = new ChapterModel();
        $chapterInfo = $chapterModel->getChapterListByComicId($comicId, 3, '', true);

        if (!empty($chapterInfo))
        {
            if ($num > 0)
            {
                $chapterInfo = array_splice($chapterInfo, sizeof($chapterInfo) - $num);
            }
            $chapterListArr = $this->preprocessChapterInfo($comicId, $chapterInfo);
        }

        return $chapterListArr;
    }

    /**
     * 预处理章节数据
     * @param  int   $comicId     作品id
     * @param  array $chapterInfo 章节信息
     * @return array 处理后的章节列表
     */
    private function preprocessChapterInfo($comicId, $chapterInfo)
    {
        $chapterList = array();

        if (empty($chapterInfo)) {
            return $chapterList;
        }

        $picModel = new PictureModel();

        //使用一次查询代替循环查询来获取有效可显示的章节列表
        $validListByPic = $picModel->getValidItemList($comicId);

        //数据预处理
        foreach ($chapterInfo as $chapter)
        {
            $picList = $picModel->getPictureList($comicId,$chapter['chapter_id']);
            $url = Utils::GetImageUrl($picList[0]['source_path'],0);
            foreach($picList as $pic)
            {
                if($pic['state'] == 3)
                {
                     $url =  Utils::makeDowloadUrl($comicId,$pic['chapter_id'],$pic['picture_id']);
                     $url = str_replace(array("swc2","swc"),array("mif600","mif2"),$url);
                     break;
                }
            }

            if (!in_array($chapter['chapter_id'], $validListByPic))
            {
                continue;
            }
            $chapterList[] = array(
                'chapter_id' => $chapter['chapter_id'],
                'fimage' => $url,
                'seq_no' => $chapter['seq_no'],
                'url' => "/ComicView/index/id/{$comicId}/cid/{$chapter['chapter_id']}",
                'ftitle' => $chapter['title'],
                'date' => date("Y.m.d", $chapter['create_time'])
            );
        }

        return $chapterList;
    }
}

$event = new Event();
$event->index();
?>
