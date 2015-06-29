<?php

//error_reporting(E_ALL);
//ini_set('display_errors',1);

include '../EventModel.class.php';
include '../EventBase.class.php';

define("ACT_PATH", dirname(__FILE__));
define('TPL_PATH', ACT_PATH."/tpl/");    

class Event extends EventBase
{
    private $comicId = 531036;

    public function __construct()
    {
        parent::__construct();
        $this->View = new View();
    }
    
    //获取题号，用于更新缓存
    private function getQuestionNum()
    {
        $questionArr = ServiceHelper::GetValueByRedisKey("ak_date_pic");
        $questionArr = json_decode($questionArr, true);
        
        return !empty($questionArr) ? $questionArr[0]["id"] : 0;
    }
    
    public function index()
    {
        //页面级缓存
        $questionNum = $this->getQuestionNum();
        /*$cacheHtml = $this->readCacheHtml($this->comicId, $questionNum);
        if (!empty($cacheHtml))
        {
            echo $cacheHtml;
            exit;
        }*/

        //人气值和收藏数
        $infoArr = $this->getComicData($this->comicId, "pgv_count, coll_count");
        $data["info"] = $infoArr;

        //AK传奇最新章节、AK传奇轮播广告
        $akLastChapterKey = "ak_lastest_chapter";
        $akLastChapterPicKey = "ak_event_pic";
        $akLoopAdsKey = "ak_loop_ads";
        $akDatePicKey = "ak_date_pic";
        $akQuestionKey = "ak_question";
        $akRuleKey = "ak_rule";
        
        $keyListArr = array($akLastChapterKey, $akLastChapterPicKey, $akLoopAdsKey, $akDatePicKey, $akQuestionKey, $akRuleKey);
        $tempArr = ServiceHelper::GetValueListByRedisKeyList($keyListArr);

        //AK传奇最新章节,后台配置
        $akLastChapterArr = !empty($tempArr) ? $tempArr[$akLastChapterKey] : array();
        $data["akLastChapter"] = $akLastChapterArr;

        //AK传奇活动图
        $akLastChapterPicArr = !empty($tempArr) ? $tempArr[$akLastChapterPicKey] : array();
        $data["akLastChapterPic"] = $akLastChapterPicArr;

        //AK传奇轮播广告
        $akLoopAdsArr = !empty($tempArr) ? $tempArr[$akLoopAdsKey] : array();
        
        //时间和礼包图
        $akDatePicArr = !empty($tempArr) ? $tempArr[$akDatePicKey] : array();
        
        //选择题
        $akQuestionArr = !empty($tempArr) ? $tempArr[$akQuestionKey] : array();
        
        //活动规则
        $akRuleArr = !empty($tempArr) ? $tempArr[$akRuleKey] : array();
        
        //AK传奇作品章节列表
        $akLastArr = array();
        $akChapterListArr = $this->getChapterList($this->comicId);
        if (!empty($akChapterListArr))
        {
            $akLastArr = $akChapterListArr[sizeof($akChapterListArr) - 1];
        }
        $data["akLast"] = $akLastArr;
        $data["akChapterList"] = $akChapterListArr;
        $total = sizeof($akChapterListArr);
        $data["total"] = $total;

        //穿越火线那些事儿的最新一话
        $cfLastestArr = array();
        $cfChapterListArr = $this->getChapterList(522930, 1);
        if (!empty($cfChapterListArr))
        {
            $cfLastestArr = $cfChapterListArr[0];
        }
        $data["cfLastest"] = $cfLastestArr;

        //雇佣兵也疯狂(第2季)的最新一话
        $soldierLastestArr = array();
        $chapterListArr = $this->getChapterList(524750, 1);
        if (!empty($chapterListArr))
        {
            $soldierLastestArr = $chapterListArr[0];
        }
        $data["soldierLastest"] = $soldierLastestArr;

        $pageSize = intval(ceil($total/20));
        $data["pageSize"] = $pageSize;

        $this->View->assign("pageSize", $pageSize);
        $this->View->assign("total", $total);

        $this->View->assign("akLastChapterArr", $akLastChapterArr);
        $this->View->assign("akLastChapterPicArr", $akLastChapterPicArr);
        $this->View->assign("akLoopAdsArr", $akLoopAdsArr);
        
        $this->View->assign("akDatePicArr", $akDatePicArr);
        $this->View->assign("akQuestionArr", $akQuestionArr);
        $this->View->assign("akRuleArr", $akRuleArr);
        
        $this->View->assign("infoArr", $infoArr);
        $this->View->assign("akLastArr", $akLastArr);
        $this->View->assign("akChapterListArr", $akChapterListArr);
        //最新20话
        $this->View->assign("ak20ChaptersArr", $total > 20 ? array_slice($akChapterListArr, $total - 20) : $akChapterListArr);

        $this->View->assign("cfLastestArr", $cfLastestArr);
        $this->View->assign("soldierLastestArr", $soldierLastestArr);

        $html = $this->View->fetch('index.shtml');

        //$this->writeCacheHtml($this->comicId, $questionNum, $html);

        echo $html;exit;
    }
   
    private function readCacheHtml($comicId, $questionNum)
    {
        if (!empty($questionNum))
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd') . "/cf_{$comicId}_{$questionNum}.html";
        }
        else 
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd') . "/cf_{$comicId}.html";
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
        
    private function writeCacheHtml($comicId, $questionNum, $html)
    {
        if (!empty($questionNum))
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd'). "/cf_{$comicId}_{$questionNum}.html";
        }
        else
        {
            $file = APP_PATH . "Runtime/Cache/" . date('Ymd'). "/cf_{$comicId}.html";
        }
        
        return file_put_contents($file, $html);
    }

    //直接显示缓存内容
    public function showCache()
    {
        $this->View->display('cf_data.html');
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
