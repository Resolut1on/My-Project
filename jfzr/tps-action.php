<?php
//ini_set('display_errors',1);
ini_set("session.save_handler","redis");
ini_set("session.save_path","tcp://10.151.12.217:6381?auth=redis@dmpt01");
ini_set('session.gc_maxlifetime', 600);
session_start();
include '../EventModel.class.php';
include '../EventBase.class.php';

class Event extends EventBase
{
    //活动后台配置，用于统计分析
    private $eventId = 1017;
    
    //枪神纪作品id
    private $comicId = 529460;
    
    //活动变更修改项开始(js文件和礼包发送文件都得修改)
    private $counterRedisKey = "";
    private $counterRedisMember = "takePacket";
    //活动默认时间，具体时间从后台配置读取
    private $startDateTime = "2015-03-11 00:00:00";
    private $endDateTime = "2015-12-31 23:25:59";
    //活动充值标识
    private $eventAid = "pc_event_tps20150315";
    //actionId、action、object活动后台配置
    private $packetArr = array(
        1 => array("actionId" => 10096, "name"=>"选择题礼包", "action"=>"take_packet", "object" => "chosen_packet_20150315"),
        2 => array("actionId" => 10097, "name"=>"新手礼包", "action"=>"click_packet", "object" => "new_packet_20150315")
    );
    //活动变更修改项结束
    
    private $sessionName = "TPS_GetPacket";

    public function __construct()
    {
        $this->counterRedisKey = "tps20150315:".date("Ymd");
        $this->getEventDatefromConf();
        parent::__construct();
    }
    
    //从后台配置获取活动的开始时间和结束时间
    private function getEventDatefromConf()
    {
        $EventConfArr = $this->getEventInfofromConf();
        
        if (!empty($EventConfArr))
        {
            $this->startDateTime = $EventConfArr[0]["startDate"] . " 00:00:00";
            $this->endDateTime = $EventConfArr[0]["endDate"] . " 23:25:59";
        }
    }
    
    //返回活动的配置数据
    private function getEventInfofromConf()
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

            echo '{"status":1}';
        }
        else if($result['code'] == -1) //达到收藏夹上限
        {
            echo '{"status":2}';
            exit();
        }
        else if($result['code'] == -2) //已经收藏
        {
            echo '{"status":3}';
            exit();
        }
        else
        {
            echo '{"status":0}';
        }
    }
    
    //取消收藏
    public function delCollect()
    {
        //验证是否登录
        $this->getLoginInfo();
                
        //验证token
        $this->checkToken(Utils::GetValue("tokenkey"), $this->uin);
        
        //检查请求来源
        $this->checkRefer();
        
        $collectionModel = new CollectionModel();

        if ($collectionModel->delCollection($this->uin, $this->comicId))
        {
            ActionHelper::UpdateUserStat($this->uin, COLL, '-');
            ActionHelper::UpdateComicStat($this->comicId, 'coll_count', '-');
            ActionHelper::RecordUserAction($this->uin, DEL_COLL_ACT, $this->comicId);
            echo '{"status":10}';
        }
        else
        {
            echo '{"status":-10}';
        }
        exit;
    }

    /**
     * 查看获取的礼包信息
     */
    private function viewGotPacket()
    {
         //验证是否登录
        $this->getLoginInfo();
        $list = array();

        $tpsEventDataArr = $this->getEventInfofromConf();
        if (empty($tpsEventDataArr))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：获取活动数据发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#ff0000;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        //活动类型
        $eventType = $tpsEventDataArr[0]["eventType"];
        $paramsArr['event_id'] = $this->eventId;
        $paramsArr['action'] = $this->packetArr[$eventType]["action"];
        $paramsArr['object'] = $this->packetArr[$eventType]["object"];
        $paramsArr['uin'] = $this->uin;
        //题号
        $paramsArr['action_val'] = $tpsEventDataArr[0]["id"];

        $packetArr = ServiceHelper::Call("event.getActionList", $paramsArr);
        
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
        
        //验证活动是否开始或者结束
        $this->checkEventTime($this->startDateTime, $this->endDateTime);
        
        //验证是否登录
        $this->getLoginInfo();
        
        //验证token
        $this->checkToken(Utils::GetValue("tokenkey"), $this->uin);
        
        $tpsEventDataArr = $this->getEventInfofromConf();
        if (empty($tpsEventDataArr))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：获取活动数据发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#ff0000;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        $questionNum = $tpsEventDataArr[0]["id"]; 
        $eventType = $tpsEventDataArr[0]["eventType"];
        $object = $this->packetArr[$eventType]["object"];
        
        $roleArr = $this->getRoleInfoByTps(80002, $this->uin, $areaId);
        
        if (!empty($roleArr) && $roleArr["ret"] == 2 && urldecode(iconv("gbk", "utf-8", urldecode($roleArr["characName"]))) == $roleName)
        {
            if ($this->addGameArea($this->uin, $areaName, $areaId, '', $roleName, $questionNum, $object))
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
     * @param type $questionNum 题号
     * @param type $object 活动的对象(后台配置)
     */
    private function addGameArea($uin, $areaName, $areaId, $roleId, $roleName, $questionNum, $object)
    {
        $uin = intval($uin);
        $areaId = intval($areaId);
        $roleId = mysql_escape_string($roleId);
        $areaName = mysql_escape_string($areaName);
        $roleName = mysql_escape_string($roleName);
        
        $sql = "insert into `tb_event_game_area`(uin,event_aid,area_id,area_name,role_name,reserve1,reserve2, ctime) values({$uin},'{$this->eventAid}',{$areaId},'{$areaName}','{$roleName}','{$questionNum}','{$object}',now());";

        $eventClient = $this->getEventClient();
        return $eventClient->execute($sql);
    }
    
    /**
     * 得到用户的大区角色数据
     * @param type $uin qq号
     * @param type $questionNum 活动题号
     * @param type $object 活动对象
     */
    private function getGameArea($uin, $questionNum, $object)
    {
        $uin = intval($uin);
        $sql = "select area_id, area_name from tb_event_game_area where uin = {$uin} and event_aid = '{$this->eventAid}' and reserve1 = {$questionNum} and reserve2 = '{$object}' limit 1;";
        
        $eventClient = $this->getEventClient("slave");
        return $eventClient->query($sql);
    }

    /**
     * 获取用户的角色
     */
    private function getRoleByAreaId()
    {
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

        $roleArr = $this->getRoleInfoByTps(80002, $this->uin, $areaId);
        
        if ($roleArr["ret"] == 2)
        {
            $roleArr["roleName"] = urldecode(iconv("gbk", "utf-8", urldecode($roleArr["characName"])));
            $roleArr["roleId"] = $this->uin;
            unset($roleArr["characName"]);
            unset($roleArr["registerTime"]);
            unset($roleArr["lastLoginTime"]);
        }
        
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
     * 回答问题领礼包
     */
    private function takePacket()
    {
        $this->checkEvent();
        $uin = $this->uin;
        
        $type = intval(Utils::GetValue("type"));
        if (!in_array($type, array(1, 2)))
        {
            echo '{"status":0,"msg":"提示：参数出现错误！"}';
            exit; 
        }
        
        //检查提交的频率
        $this->checkSubmitFrequency($this->sessionName, 1);
        
        //活动配置数据
        $tpsEventDataArr = $this->getEventInfofromConf();
        if (empty($tpsEventDataArr))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：获取活动数据发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#ff0000;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        $questionNum = $tpsEventDataArr[0]["id"]; 
        $eventType = $tpsEventDataArr[0]["eventType"];
        //当后台已经更换题型，但是用户未刷新页面，所以需要验证题型是否一致
        if ($type != $eventType)
        {
            echo '{"status":0,"msg":"提示：活动题型有变化，请刷新页面后再继续！"}';
            exit; 
        }
        
        $action = $this->packetArr[$eventType]["action"];
        $object = $this->packetArr[$eventType]["object"];
        
        //步骤:
        //1.是否选大区
        $areaArr = $this->getGameArea($uin, $questionNum, $object);
        if (empty($areaArr))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }
        
        //是否正确回答了问题
        $paramsArr = array('event_id' => $this->eventId, 'action' => "question", 'object' => "answer", 'uin' => $this->uin, 'action_val' => $questionNum, 'reserve1' => '', 'reserve2' => 1);
        $answerArr = ServiceHelper::Call("event.getAction", $paramsArr);
        if (empty($answerArr))
        {
            echo '{"status": 0, "msg":"提示：请先回答问题再来领取礼包！"}';
            exit;
        }

        //2.判断当天是否还有礼包剩余
        $usedPacketTotal = $this->getPacketTotalByDay();
        if ($usedPacketTotal >= 10000)
        {
            $this->emptyPacket();
        }

        //3.判断是否已经领取当前问题的礼包
        $paramsArr = array('event_id' => $this->eventId, 'action' => $action, 'object' => $object, 'uin' => $this->uin, 'action_val' => $questionNum);
        $isGotPacketArr = ServiceHelper::Call("event.getAction", $paramsArr);
        if (!empty($isGotPacketArr))
        {
            echo json_encode(array("status"=>0, "msg"=>"提示：您已经领取过礼包了，请点<a href='javascript:SubQsj.viewPacket();' style='color:#ff0000;width:111px;display:inline;font-size: 19px;font-family:microsoft yahei'>【礼包信息】</a>查看！"));   
            exit;
        }
        
        //4.领取礼包，离线发送礼包
        $paramsArr['reserve1'] = date("Y-m-d");
        $paramsArr['ctime'] = time();
        $rs = ServiceHelper::Call("event.addAction", $paramsArr);
        
        if ($rs["status"] == 2)
        {
            $this->setPacketTotalByDay();
            echo '{"status": 1, "msg":"提示：领取礼包成功，24小时内直接发放到绑定的游戏角色！"}';
        }
        else
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#ff0000;width:111px;display:inline;font-family:microsoft yahei;font-size:19px;" target="_blank">【反馈建议】</a>投诉！'));
        }
        exit;
    }
    
    /**
     * 领取新手礼包
     */
    private function getNewPacket()
    {
        $this->checkEvent();
        $uin = $this->uin;
        
        $type = intval(Utils::GetValue("type"));
        if (!in_array($type, array(1, 2)))
        {
            echo '{"status":0,"msg":"提示：参数出现错误！"}';
            exit; 
        }

        //活动配置数据
        $tpsEventDataArr = $this->getEventInfofromConf();
        if (empty($tpsEventDataArr))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：获取活动数据发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#ff0000;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        $questionNum = $tpsEventDataArr[0]["id"]; 
        $eventType = $tpsEventDataArr[0]["eventType"];
        //当后台已经更换题型，但是用户未刷新页面，所以需要验证题型是否一致
        if ($type != $eventType)
        {
            echo '{"status":0,"msg":"提示：活动题型有变化，请刷新页面后再继续！"}';
            exit; 
        }
        
        $action = $this->packetArr[$eventType]["action"];
        $object = $this->packetArr[$eventType]["object"];
        $regdate = $tpsEventDataArr[0]["regdate"];

        //判断是否选大区
        $areaArr = $this->getGameArea($uin, $questionNum, $object);
        if (empty($areaArr))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }
        $areaId = $areaArr[0]["area_id"];

        //步骤:
        //1.调用接口得到玩家是否注册过枪神纪游戏，以及判断玩家是新手。
        $infoArr = $this->getRoleInfoByTps(80002, $uin, $areaId);
        
        if ($infoArr["ret"] == 1)
        {
            //没有注册枪神纪的用户
            echo '{"status":0,"msg":"提示：您不是新注册用户（'.$regdate.'后首次注册），无法领取，请先去游戏注册角色！"}';
            exit;
        }
        else if ($infoArr["ret"] == 2)
        {
            if ($infoArr["registerTime"] < strtotime($regdate))
            {
                //不是目标用户，不发
                echo '{"status":0,"msg":"提示：您不符合条件，无法领取哦！"}';
                exit; 
            }
        }
        
        //3.判断当日是否还有礼包剩余，每天限量10000个
        $usedPacketTotal = $this->getPacketTotalByDay();
        if ($usedPacketTotal >= 10000)
        {
            $this->emptyPacket();
        }

        //4.判断是否已经领取过礼包
        $paramsArr = array('event_id' => $this->eventId, 'action' => $action, 'object' => $object, 'uin' => $this->uin, 'action_val' => $questionNum);
        $isGotPacketArr = ServiceHelper::Call("event.getAction", $paramsArr);
        
        if (!empty($isGotPacketArr))
        {
            echo json_encode(array("status"=>0, "msg"=>"提示：您已经领取过礼包了，请点<a href='javascript:SubQsj.viewPacket();' style='color:#ff0000;width:111px;display:inline;font-size: 19px;'>【礼包信息】</a>查看！"));   
            exit;
        }
        
        //5.领取礼包，离线发送礼包
        $paramsArr['ctime'] = time();
        $paramsArr['reserve1'] = date("Y-m-d");
        $rs = ServiceHelper::Call("event.addAction", $paramsArr);
        
        if ($rs["status"] == 2)
        {
            $this->setPacketTotalByDay();
            echo '{"status": 1, "msg":"提示：领取礼包成功，24小时内直接发放到绑定的游戏角色！"}';
        }
        else
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#ff0000;width:111px;display:inline;font-size:19px;" target="_blank">【反馈建议】</a>投诉！'));
        }
        exit;
    }

    /**
     * 获取当天领取的礼包数量
     */
    private function getPacketTotalByDay()
    {
        $paramsArr = array('key'=>$this->counterRedisKey, 'start'=>'0', 'end'=> -1, 'withScores'=>1, 'terminal'=>10);
        $totalArr = ServiceHelper::Call("redis.zrange", $paramsArr);

        return !empty($totalArr[$this->counterRedisMember]) ? intval($totalArr[$this->counterRedisMember]) : 0;
    }
    
    /**
     * 新增当天领取的礼包数量
     */
    private function setPacketTotalByDay()
    {
        $paramsArr = array('key'=>$this->counterRedisKey, 'member'=>$this->counterRedisMember, 'terminal'=>10);
        ServiceHelper::Call("redis.zincrby", $paramsArr);
    }

    /**
     * 获取选择题内容
     */
    private function getQuestionFromConf()
    {
        //$questionArr = ServiceHelper::GetValueByKey("tps_event_question");
        $questionArr = ServiceHelper::GetValueByRedisKey("tps_event_question");
        return json_decode($questionArr, true);
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
        
        $type = intval(Utils::GetValue("type"));
        if (!in_array($type, array(1, 2)))
        {
            echo '{"status":0,"msg":"提示：参数出现错误！"}';
            exit; 
        }
        
        //验证活动
        $this->checkEvent();
        
        //获取活动配置数据
        $tpsEventDataArr = $this->getEventInfofromConf();
        $questionArr = $this->getQuestionFromConf();
        if (empty($tpsEventDataArr) || empty($questionArr))
        {
            echo json_encode(array("status"=>0, "msg"=>'提示：获取活动数据发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744"  style="color:#ff0000;width:111px;display:inline;font-family:microsoft yahei;font-size:16px;" target="_blank">【反馈建议】</a>投诉！'));
            exit;
        }
        
        $questionNum = $tpsEventDataArr[0]["id"]; 
        $eventType = $tpsEventDataArr[0]["eventType"];
        //当后台已经更换题型，但是用户未刷新页面，所以需要验证题型是否一致
        if ($type != $eventType)
        {
            echo '{"status":0,"msg":"提示：活动题型有变化，请刷新页面后再继续！"}';
            exit; 
        }
        
        $action = $this->packetArr[$eventType]["action"];
        $object = $this->packetArr[$eventType]["object"];
        
        //答题规则，每天限1次，无论对错，回答正确不能再回答
        //判断是否答对此题记录，action_val:题号; reserve1:答题时间; reserve2:答题结果
        $paramsArr = array('event_id' => $this->eventId, 'action' => "question", 'object' => "answer", 'uin' => $this->uin, 'action_val' => $questionNum, 'reserve1' => '', 'reserve2' => 1);
        $answerArr = ServiceHelper::Call("event.getAction", $paramsArr);
        
        if (!empty($answerArr) && $answerArr["reserve2"] == 1)
        {
            //正确回答了问题，但是没领取礼包的情况
            $paramsArr = array('event_id' => $this->eventId, 'action' => $action, 'object' => $object, 'uin' => $this->uin, 'action_val' => $questionNum);
            $isGotPacketArr = ServiceHelper::Call("event.getAction", $paramsArr);

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
        $paramsArr = array('event_id' => $this->eventId, 'action' => "question", 'object' => "answer", 'uin' => $this->uin, 'action_val' => $questionNum, 'reserve1' => date("Y-m-d"));
        $logArr = ServiceHelper::Call("event.getAction", $paramsArr);
        
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
        
        $paramsArr['ctime'] = time();
        
        if ($isCorrect)
        {
            //记录答案
            $paramsArr['reserve2'] = 1;
            $rs = ServiceHelper::Call("event.addAction", $paramsArr);
            
            if ($rs["status"] == 2)
            {
                echo '{"status": 1, "msg":"干得漂亮，礼包已激活。速速领取吧！"}';
                exit;
            }
        }
        else
        {
            $paramsArr['reserve2'] = -1;
            $rs = ServiceHelper::Call("event.addAction", $paramsArr);
            
            if ($rs["status"] == 2)
            {
                echo '{"status":0, "msg":"差一点就答对了！再回顾下漫画，明天继续哦！"}';
                exit;
            }
        }
        echo '{"status":0, "msg":"写入数据发生错误！"}';
    }

    public function work()
    {
        $action = Utils::GetValue('action');
        switch ($action)
        {
            case 'takePacket':
                //回答问题领取礼包
                $this->takePacket();
                break;
            case 'getPacket':
                //领取新手礼包
                $this->getNewPacket();
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
            case 'delCollect':
                //取消收藏作品
                $this->delCollect();
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