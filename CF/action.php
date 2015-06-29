<?php
//ini_set('display_errors',1);
ini_set("session.save_handler","redis");
ini_set("session.save_path","tcp://10.151.12.217:6381?auth=redis@dmpt01");
session_start();
include '../EventModel.class.php';
include '../EventBase.class.php';

define('CF_COLL_COOKIE', 'cf_comic_coll');

class Event extends EventBase
{
    //活动后台配置，用于统计分析
    private $eventId = 1013;
    
    //ak传奇作品id
    private $comicId = 531036;
    
    //活动变更修改项开始(js文件和礼包发送文件都得修改)
    private $counterRedisKey = "";
    private $counterRedisMember = "takePacket";
    private $startDateTime = "2015-03-02 00:00:00";
    private $endDateTime = "2015-04-30 23:25:59";
    //活动充值标识
    private $eventAid = "pc_event_cf20150302";
    //actionId、actionTag、object活动后台配置
    private $packetArr = array(
        1 => array("actionId" => 10092, "name"=>"福利礼包", "actionTag"=>"take_packet", "object" => "packet_20150302")
    );
    //活动变更修改项结束
    
    private $sessionName = "CF20150130_GetPacket";

    public function __construct()
    {
        $this->counterRedisKey = "cf20150302:".date("Ymd");
        $this->getEventDatefromConf();
        parent::__construct();
    }
    
    //从后台配置获取活动的开始时间和结束时间
    private function getEventDatefromConf()
    {
        $EventConfArr = ServiceHelper::GetValueByRedisKey("ak_date_pic");
        $EventConfArr = json_decode($EventConfArr, true);

        if (!empty($EventConfArr))
        {
            $this->startDateTime = $EventConfArr[0]["startDate"] . " 00:00:00";
            $this->endDateTime = $EventConfArr[0]["endDate"] . " 23:25:59";
        }
    }
    
    /**
     * 判读活动是否开始或者结束
     */
    private function checkTime($startDateTime, $endDateTime)
    {
        $time = time();
        if ($time < strtotime($startDateTime))
        {
            echo '{"status":-96, "msg":"活动未开放，请在活动期间参与！"}';
            exit;
        }

        if ($time > strtotime($endDateTime))
        {
            echo '{"status":-95, "msg":"亲爱的小伙伴，本次全民礼包活动已经结束。请关注下一期礼包活动哦！多多关注CF漫画站，有更多礼包惊喜免费拿哦！"}';
            exit;
        }
    }
    
    /**
     * 活动验证
     */
    private function checkEvent()
    {
        //验证是否登录
        $this->getLoginInfo();
        
        //验证活动是否开始或者结束
        $this->checkTime($this->startDateTime, $this->endDateTime);
        
        //验证token
        $this->checkToken(Utils::GetValue("tokenkey"), $this->uin);
        
        //检查请求来源
        $this->checkRefer();
    }

    /**
     * 验证是否登录，以及获取用户登录后在活动中的数据
     */
    private function getEventInfo()
    {
        //线上有问题的时候，设置为默认已领完
        /*$resArr["status"] = 1;
          $resArr["isEnd"] = 1;
        echo json_encode($resArr);exit;*/

         //默认活动结束
        $resArr["isEnd"] = 1;
        
        //isCheck = 1是点页面上的"登录"按钮，需验证是否登录
        $isLogin = Utils::GetValue("isLogin");
        $isLogin = intval($isLogin);
        
        if (!in_array($isLogin, array(0,1)))
        {
            $isLogin = 1;
        }
        
        //活动进行中
        if (time() < strtotime($this->endDateTime))
        {
            //需先验证是否登录
           $this->getLoginInfo();
          
            $resArr["isEnd"] = 0;
            $resArr['isCollect'] = $this->isCollect($this->uin);
        }
        else
        {
            $ptLoginInfo = APIHelper::CheckPtLogin();
            if (!empty($ptLoginInfo))
            {
                $resArr["isLogin"] = 1;
                $uin = isset($ptLoginInfo['Uin']) ? sprintf("%.0f",$ptLoginInfo['Uin']) : 0;
                $resArr['isCollect'] = $this->isCollect($uin);
            }
            else
            {
                if ($isLogin == 1)
                {
                    echo '{"status": -99, "msg":"未登录"}';
                    exit;
                }
                else
                {
                    $resArr["isLogin"] = 0;
                }
            }
        }
        
        $resArr["token"] = $this->setToken();
        $resArr["status"] = 1;
        echo json_encode($resArr);
        exit;
    }
    
    //是否收藏此漫画作品
    private function isCollect($uin)
    {
        //从cookie判断是否已经收藏
        $cookieName = CF_COLL_COOKIE . '_' . $uin;
        $value = Utils::GetCookie($cookieName);
        return intval($value);
    }

    //添加收藏作品
    protected function addCollect()
    {
        //验证是否登录
        $this->getLoginInfo();
        
        //验证token
        $this->checkToken(Utils::GetValue("tokenkey"), $this->uin);
        
        //检查请求来源
        $this->checkRefer();

        $collectionModel = new CollectionModel();
        $collectionList = $collectionModel->getUserCollectionList($this->uin);
        if ($collectionList === false)
        {
            echo '{"status":0}';
            exit();
        }

        $result = $collectionModel->addCollection($this->uin, $this->comicId);

        if ($result['ret'] == 2)
        {
            $statModel = new StatModel();
            $statModel->updStatByUinType($this->uin, COLL, count($collectionList) + 1);

            ActionHelper::UpdateComicStat($this->comicId, 'coll_count', '+');
            ActionHelper::RecordUserAction($this->uin, COLL_ACT, $this->comicId);
            ActionHelper::UpdateUserPoint($this->uin, COLL_ACT);

            $cookieName = CF_COLL_COOKIE . '_' . $this->uin;
            Utils::SetCookie($cookieName, 1, time()+2592000);
            
            echo '{"status":1}';
        }
        else if($result['code'] == -1) //达到收藏夹上限
        {
            echo '{"status":2}';
            exit();
        }
        else if($result['code'] == -2) //已经收藏
        {
            $cookieName = CF_COLL_COOKIE . '_' . $this->uin;
            Utils::SetCookie($cookieName, 1, time()+2592000);
            echo '{"status":3}';
            exit();
        }
        else
        {
            echo '{"status":0}';
        }
    }

    /**
     * 查看获取的礼包信息
     */
    private function viewGotPacket()
    {
         //验证是否登录
        $this->getLoginInfo();
        $list = array();
        
        $num = $this->getQuestionNum();
        if (empty($num))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：获取题号错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        $packetArr = $this->getActionList($this->eventId, "take_packet", $this->packetArr[1]["object"], $this->uin, $num);
        
        if (!empty($packetArr))
        {
            $tempArr = $packetArr["data"];
            foreach ($tempArr as $key => $value)
            {
                $tempArr[$key]["name"] = $tempArr[$key]["object"];
                $tempArr[$key]["date"] = date("Y-m-d", $tempArr[$key]["ctime"]);
                $tempArr[$key]["remark"] = "活动获得,领取后24小时内到游戏内查询";
                unset($tempArr[$key]["id"]);
                unset($tempArr[$key]["action_id"]);
                unset($tempArr[$key]["uin"]);
                unset($tempArr[$key]["object"]);
                unset($tempArr[$key]["action_vol"]);
                unset($tempArr[$key]["action_val"]);
                unset($tempArr[$key]["terminal"]);
                unset($tempArr[$key]["ctime"]);
                unset($tempArr[$key]["mtime"]);
                unset($tempArr[$key]["state"]);
                unset($tempArr[$key]["reserve1"]);
                unset($tempArr[$key]["reserve2"]);
                unset($tempArr[$key]["nickname"]);
            }
            $list = $tempArr;
        }
        
        echo json_encode(array("status"=>1, "list"=>$list));
        exit;
    }

    ////////////////////////////////////////////角色区开始////////////////////////////////////////////////
    /**
     * 绑定角色
     */
    private function confirmRole()
    {
        $areaId = Utils::GetValue("areaId");
        if (empty($areaId))
        {
            $this->emptyAreaId();
        }
        $areaId = intval($areaId);
        
        $areaName = Utils::GetValue("areaName");
        if (empty($areaName))
        {
            $this->emptyAreaName();
        }
        
        $roleName = Utils::GetValue("roleName");
        if (empty($roleName))
        {
            $this->emptyRole();
        }
        
        //验证是否登录
        $this->getLoginInfo();
        
        $num = $this->getQuestionNum();
        if (empty($num))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：获取题号错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        //验证活动是否开始或者结束
        $this->checkEventTime($this->startDateTime, $this->endDateTime);

        $roleArr = $this->getRoleByCF(12002, $this->uin, $areaId);
        
        if (!empty($roleArr) && $roleArr["ret"] == 2 && $roleArr["nickName"] == $roleName)
        {
            if ($this->addGameArea($this->uin, $areaName, $areaId, '', $roleName, $num))
            {
                echo '{"status":1}';
            }
            else
            {
                echo '{"status":0, "msg": "绑定角色发生错误，请稍后再试！~"}';
            }
        }
        else
        {
            $this->noneRole();
        }
        exit;
    }
    
    /**
     * 添加用户游戏大区
     * @param type $uin QQ
     * @param type $areaName 区域名
     * @param type $areaId 区域id
     * @param type $roleName 角色名
     */
    private function addGameArea($uin, $areaName, $areaId, $roleId, $roleName, $num)
    {
        $uin = intval($uin);
        $areaId = intval($areaId);
        $roleId = mysql_escape_string($roleId);
        $areaName = mysql_escape_string($areaName);
        $roleName = mysql_escape_string($roleName);
        
        $sql = "insert into `tb_event_game_area`(uin,event_aid,area_id,area_name,role_name,reserve1,ctime) values({$uin},'{$this->eventAid}',{$areaId},'{$areaName}','{$roleName}','{$num}',now());";

        $eventClient = $this->getEventClient();
        return $eventClient->execute($sql);
    }
    
    /**
     * 得到某一用户的大区角色数据
     */
    private function getGameArea($uin, $num)
    {
        $uin = intval($uin);
        $sql = "select area_id, area_name from tb_event_game_area where uin = {$uin} and event_aid = '{$this->eventAid}' and reserve1 = {$num} limit 1;";
        
        $eventClient = $this->getEventClient("slave");
        return $eventClient->query($sql);
    }

    /**
     * 获取用户的角色
     */
    private function getRoleByAreaId()
    {
        $cmd = 12002;
        $areaId = Utils::GetValue("areaId");
        if (empty($areaId))
        {
            echo '{"status":-1}';
            exit;
        }
        $areaId = intval($areaId);
        
        //验证是否登录
        $this->getLoginInfo();
        
        //验证活动是否开始或者结束
        $this->checkEventTime($this->startDateTime, $this->endDateTime);

        $roleArr = $this->getRoleByCF($cmd, $this->uin, $areaId);
        
        echo json_encode(array("status"=>1,"role"=>$roleArr));
        exit;
    }
    ////////////////////////////////////////////角色区结束////////////////////////////////////////////////

    /**
     * 检查提交的频率
     * @param type $sessionName 保存提交时间的session名
     * @param type $diffTime 2次提交的间隔时间
     */
    private function checkSubmitFrequency($sessionName, $diffTime)
    {
        if (isset($_SESSION[$sessionName]))
        {
            $submitTime = intval($_SESSION[$sessionName]);
            $_SESSION[$sessionName] = time();
            
            if (time() - $submitTime <= $diffTime)
            {
                echo '{"status":0, "msg":"提交太频繁，休息一下"}';
                exit;
            }
        }
        else
        {
            $_SESSION[$sessionName] = time();
        }
    }
    
    /**
     * 领取礼包
     */
    private function takePacket()
    {
        $this->checkEvent();
        
        //检查提交的频率
        $this->checkSubmitFrequency($this->sessionName, 1);
        
        $uin = $this->uin;
        
        $num = $this->getQuestionNum();
        if (empty($num))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        //步骤:
        //1.是否选大区
        $areaArr = $this->getGameArea($uin, $num);
        if (empty($areaArr))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }
        
        $answerArr = $this->getAction($this->eventId, "question", "answer", $this->uin, $num, '', 1);
        if (empty($answerArr))
        {
            echo '{"status": 0, "msg":"提示：请先回答问题再来领取礼包！"}';
            exit;
        }

        //2.判断当天是否还有礼包剩余
        $types = 1;
        $usedPacketTotal = $this->getPacketTotalByDay();
        if ($usedPacketTotal >= 9900)
        {
            $this->emptyPacket();
        }

        //3.判断是否已经领取当前问题的礼包
        $isGotPacketArr = $this->getAction($this->eventId, $this->packetArr[$types]["actionTag"], $this->packetArr[$types]["object"], $uin, '', $num);
        if (!empty($isGotPacketArr))
        {
            echo json_encode(array("status"=>0, "msg"=>"提示：您已经领取过礼包了，请点<a href='javascript:Event.viewPacket();' style='color:rgb(232,182,118);width:111px;display:inline;font-size: 16px;font-family:microsoft yahei'>【礼包信息】</a>查看！"));   
            exit;
        }
        
        //4.领取礼包，离线发送礼包
        //$num是问题的编号
        if ($this->addAction($this->eventId, $uin, $this->packetArr[$types]["actionTag"], $this->packetArr[$types]["object"], '',$num) == 2)
        {
            
            $this->setPacketTotalByDay();
            echo '{"status": 1, "msg":"提示：领取礼包成功，24小时内直接发放到绑定的游戏角色！"}';
        }
        else
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
        }
        exit;
    }
    
    private function getPacketTotalByDay()
    {
        $total = 0;
        $paramsArr = array('key'=>$this->counterRedisKey, 'start'=>'0', 'end'=> -1, 'withScores'=>1, 'terminal'=>10);
        $totalArr = ServiceHelper::Call("redis.zrange", $paramsArr);

        if (!empty($totalArr[$this->counterRedisMember]))
        {
            $total = intval($totalArr[$this->counterRedisMember]);
        }
        else 
        {
            $total = $this->getPacketTotalByDayFromDB();
        }
        //return !empty($totalArr[$this->counterRedisMember]) ? intval($totalArr[$this->counterRedisMember]) : 0;
        return $total;
    }
    
    //从数据库中查询当天已经领取礼包的人数
    private function getPacketTotalByDayFromDB()
    {
        $date = date("Y-m-d"). " 00:00:00";
        $time = strtotime($date);
        $sql = "SELECT count(*) as num FROM `tb_event_action_92` WHERE action_id = 10092 and object = 'packet_20150302' and `terminal` = 1 and ctime >= {$time};";
        $eventActionClient = DBHelper::GenerateEventActionClient();
        
        $totalArr = $eventActionClient->query($sql);
        return !empty($totalArr) ? $totalArr[0]["num"] : 0;
    }
    
    private function setPacketTotalByDay()
    {
        $paramsArr = array('key'=>$this->counterRedisKey, 'member'=>$this->counterRedisMember, 'terminal'=>10);
        ServiceHelper::Call("redis.zincrby", $paramsArr);
    }

    private function getQuestionNum()
    {
        $questionArr = ServiceHelper::GetValueByRedisKey("ak_date_pic");
        $questionArr = json_decode($questionArr, true);
        
        return !empty($questionArr) ? $questionArr[0]["id"] : 0;
    }

    /**
     * 回答问题
     */
    private function reply()
    {
        $answer = Utils::GetValue("answer");
        $answer = trim($answer);
        if (empty($answer))
        {
            echo '{"status":0, "msg":"请选择答案"}';
            exit;
        }
        
        //验证活动
        $this->checkEvent();
        
        //获取后台活动配置数据
        $akDatePicKey = "ak_date_pic";
        $akQuestionKey = "ak_question";
        $keyListArr = array($akDatePicKey, $akQuestionKey);
        $tempArr = ServiceHelper::GetValueListByRedisKeyList($keyListArr);
        
        //选择题编号
        $number = !empty($tempArr) ? $tempArr[$akDatePicKey][0]["id"] : 0;
        $questionArr = !empty($tempArr) ? $tempArr[$akQuestionKey] : array();
        if ($number == 0 || empty($questionArr))
        {
            echo '{"status":0, "msg":"获取题目出现错误！"}';
            exit;
        }
        
        //答题规则，每天限1次，无论对错，回答正确不能再回答
        //判断是否答对此题记录
        //reserve1字段存储回答问题的日期，reserve2存储答题结果
        $answerArr = $this->getAction($this->eventId, "question", "answer", $this->uin, $number, '', 1);
        if (!empty($answerArr) && $answerArr["reserve2"] == 1)
        {
            $isGotPacketArr = $this->getAction($this->eventId, $this->packetArr[1]["actionTag"], $this->packetArr[1]["object"], $this->uin, '', $number);
            if (empty($isGotPacketArr))
            {
                echo '{"status":2, "msg":"您已经回答过此题，快去领取礼包吧！"}';
                exit;
            }
            else
            {
                echo '{"status":0, "msg":"您已经回答过此题，期待下次挑战吧！"}';
                exit;
            }
        }
        
        //当天是否有答题记录
        $logArr = $this->getAction($this->eventId, "question", "answer", $this->uin, $number, date("Y-m-d"));
        if (!empty($logArr) && $logArr["reserve2"] == -1)
        {
            echo '{"status":0, "msg":"你今天回答机会已经用完了，明天继续哦！"}';
            exit;
        }
        
        //答题
        $isCorrect = 0;
        foreach ($questionArr as $value)
        {
            if (trim($value["title"]) == $answer && trim($value["answer"]) == 1)
            {
                $isCorrect = 1;
                break;
            }
        }
        
        if ($isCorrect)
        {
            //记录答案
            if ($this->addAction($this->eventId, $this->uin, "question", "answer", $number, date("Y-m-d"), 1) == 2)
            {
                echo '{"status": 1, "msg":"干得漂亮，礼包已激活。速速领取吧！"}';
                exit;
            }
        }
        else
        {
            if ($this->addAction($this->eventId, $this->uin, "question", "answer", $number, date("Y-m-d"), -1) == 2)
            {
                echo '{"status":0, "msg":"差一点就答对了！再回顾下漫画，明天继续哦！"}';
                exit;
            }
        }
        echo '{"status":0, "msg":"写入数据发生错误！"}';
    }


    ////////////////////////////////////////////调用接口写入数据开始////////////////////////////////////////////////
    /**
     * 新增用户领取礼包数据
     * @param type $eventId 活动后台配置的活动id
     * @param type $uin QQ号
     * @param type $actionTag 活动行为标记
     * @param type $object 活动对象
     * @param type $actionVal cdkey字符串
     */
    private function addAction($eventId, $uin, $actionTag, $object, $actionVal, $reserve1 = '', $reserve2 = '') 
    {
        $params = array();
        $params['event_id'] = $eventId;
        $params['action'] = $actionTag;
        $params['uin'] = $uin;
        $params['object'] = $object;
        $params['action_val'] = $actionVal;
        $params['action_vol'] = 0;
        $params['terminal'] = 1;
        $params['ctime'] = time();
        $params['state'] = 1;
        if (!empty($reserve1))
        {
            $params['reserve1'] = $reserve1;
        }
        if (!empty($reserve2))
        {
            $params['reserve2'] = $reserve2;
        }
        $params['unique'] = 0;

        $ret = ServiceHelper::Call("event.addAction", $params);
        return !empty($ret) ? intval($ret['status']) : 0;
    }

    /**
     * 获取用户某一行为的记录
     * @param type $eventId 活动后台配置的活动id
     * @param type $actionTag 活动行为标记
     * @param type $uin QQ号
     * @param type $object 活动对象
     */
    private function getAction($eventId, $actionTag, $object, $uin, $actionVal = '', $reserve1 = '', $reserve2 = '')
    {
        $params = array();
        $params['event_id'] = $eventId;
        $params['action'] = $actionTag;
        $params['uin'] = $uin;
        $params['object'] = $object;
        $params['terminal'] = 1;
        $params['state'] =  1;
        if (!empty($actionVal))
        {
            $params['action_val'] =  $actionVal;
        }
        if (!empty($reserve1))
        {
            $params['reserve1'] =  $reserve1;
        }
        if (!empty($reserve2))
        {
            $params['reserve2'] =  $reserve2;
        }
        
        $actionArr = ServiceHelper::Call("event.getAction", $params);

        return $actionArr;
    }

    /**
     * 获取礼包消耗数量
     * @param type $eventId 活动后台配置的活动id
     * @param type $actionTag 活动行为标记
     * @param type $object 活动对象
     */
    private function getActionList($eventId, $actionTag, $object, $uin = 0, $reserve1 = '')
    {
        $params = array();
        $params['event_id'] = $eventId;
        $params['action'] = $actionTag;
        $params['terminal'] = 1;
        $params['state'] = 1;
        
        if ($uin > 0)
        {
            $params['uin'] = $uin;
        }
        if (!empty($object))
        {
            $params['object'] = $object;
        }
        if (!empty($reserve1))
        {
            $params['reserve1'] = $reserve1;
        }
        
        $actionArr = ServiceHelper::Call("event.getActionList", $params);

        return $actionArr;
    }
    
    public function work()
    {
        $action = Utils::GetValue('action');
        switch ($action)
        {
            case 'eventInfo':
                //登录
                $this->getEventInfo();
                break;
            case 'takePacket':
                //领取礼包
                $this->takePacket();
                break;
            case 'viewPacket':
                $this->viewGotPacket();
                break;
            case 'confirmRole':
                //绑定角色
                $this->confirmRole();
                break;
            case 'getRoleByAreaId':
                //通过区域id获取角色
                $this->getRoleByAreaId();
                break;
            case 'addCollect':
                //收藏作品
                $this->addCollect();
                break;
            case 'reply':
                //回答问题
                $this->reply();
                break;
	    default:
	       break;
        }
    }
}

$event = new Event();
$event->work();
?>
