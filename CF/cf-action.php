<?php
//ini_set('display_errors',1);
ini_set("session.save_handler","redis");
ini_set("session.save_path","tcp://10.151.12.217:6381?auth=redis@dmpt01");
ini_set('session.gc_maxlifetime', 900);
session_start();
include '../EventModel.class.php';
include '../EventBase.class.php';

class Event extends EventBase
{
    //活动后台配置，用于统计分析
    private $eventId = 1045;
    private $startDateTime = "2015-07-04 00:00:00";
    private $endDateTime = "2015-08-13 23:25:59";
    private $eventAid = "pc_event_cf201507";
    private $packetArr = array(
        1 => array("actionId" => 10171, "name"=>"专属礼包", "maxNum" => 1499000, "action"=>"get", "object" => "common_packet"),
        2 => array("actionId" => 10173, "name"=>"特权礼包", "maxNum" => 499000, "action"=>"take_packet", "object" => "vip_packet"),
        3 => array("actionId" => 10170, "name"=>"回流礼包", "maxNum" => 499000, "action"=>"take", "object" => "old_packet"),
        4 => array("actionId" => 10184, "name"=>"新手专属礼包", "maxNum" => 400000, "action"=>"fetch", "object" => "new"),
    );
    private $lastLoginDateTime = "2015-07-01";
    private $regDateTime = "2015-07-20";

    //老友礼包消耗总量redsi key
    private $oldPacketRedisKey = "oldPacketKey";
    private $oldPacketRedisMember = "oldPacketMember";

    //专属礼包消耗总量redsi key
    private $newPacketRedisKey = "newPacketKey";
    private $newPacketRedisMember = "newPacketMember";

    //vip礼包消耗总量redsi key
    private $vipPacketRedisKey = "vipPacketKey";
    private $vipPacketRedisMember = "vipPacketMember";
    
    //新手专属礼包消耗总量redsi key
    private $newComerPacketRedisKey = "newComerPacketKey";
    private $newComerPacketRedisMember = "newComerPacketMember";
    
    public function __construct()
    {
        parent::__construct();
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
            echo '{"status":-95, "msg":"活动已经结束，敬请期待更多腾讯动漫&CF联合活动！"}';
            exit;
        }
    }
    
    /**
     * 活动验证
     */
    private function checkEvent()
    {
        //验证活动是否开始或者结束
        $this->checkTime($this->startDateTime, $this->endDateTime);
        
        //验证token
        //$this->checkToken(Utils::GetValue("tokenkey"), $this->uin);
        
        //检查请求来源
        $this->checkRefer();

        //验证是否登录
        $this->getLoginInfo();
    }

    //开通vip
    protected function openVip()
    {
        //验证活动是否开始或者结束
        $this->checkTime($this->startDateTime, $this->endDateTime);

        //验证是否登录
        $this->getLoginInfo();

        echo json_encode(array("status" => 1, "uin" => $this->uin));
        exit;
    }

    //绑定角色
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
        
        if ($this->addAreaIdToSession($this->uin, $areaId))
        {
            echo '{"status":1}';
        }
        else
        {
            echo '{"status":0, "msg": "绑定角色发生错误，请稍后再试！~"}';
        }
        exit;
    }

    //获取用户的角色
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
        
        //验证活动是否开始或者结束
        $this->checkEventTime($this->startDateTime, $this->endDateTime);
        
        //验证是否登录
        $this->getLoginInfo();
        
        $nickName = $this->getRoleByAreaIdFromRedis($this->uin, $areaId);
        if (empty($nickName))
        {
            //redis读取失败时或者第一次读取时再读取一次
            $nickName = $this->getRoleByAreaIdFromRedis($this->uin, $areaId);
            if (empty($nickName))
            {
                $roleArr = $this->getRoleByCF($cmd, $this->uin, $areaId);
                if (!empty($roleArr) && $roleArr["ret"] == 2)
                {
                    $this->setRoleNameToRedis($this->uin, $areaId, $roleArr["nickName"]);
                }
                $this->clickMonitor(638460);
            }
            else
            {
                $roleArr = array("ret" => 2, "nickName" => $nickName);
            }
        }
        else
        {
            $roleArr = array("ret" => 2, "nickName" => $nickName);
        }
        $this->clickMonitor(638461);

        echo json_encode(array("status"=>1,"role"=>$roleArr));
        exit;
    }
    
    /**
     * 调用接口得到玩家是否注册过CF游戏，以及判断玩家是新手还是老手 
     * 返回值 0:未注册 1:新手 2:老手
     * @param type $uin qq
     */
    private function getUserInfoByCF($uin, $areaId)
    {
        //调用接口实现
        $cmd = 12022;
        $infoArr = $this->getRoleByCF($cmd, $uin, $areaId);
        
        if ($infoArr["ret"] == 1)
        {
            $level = 0;
        }
        else if ($infoArr["ret"] == 2)
        {
            if (date("Y", strtotime($infoArr["lastPlayDate"])) != 3000 && strtotime($infoArr["lastPlayDate"]) <= strtotime($this->lastLoginDateTime))
            {
                //流失老用户
                $level = 2;
            }
            else if (strtotime($infoArr["regDate"]) >= strtotime($this->regDateTime))
            {
                //新手用户
                $level = 3;
            }
            else
            {
                //新手或者不是目标用户，不发
                $level = 1;
            }
        }
        
        return $level;
    }

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
                echo '{"status":0, "msg":"提交太频繁，休息一下！"}';
                exit;
            }
        }
        else
        {
            $_SESSION[$sessionName] = time();
        }
    }
    
    //新手礼包
    private function getCommonPacket()
    {
        $this->checkEvent();
        $uin = $this->uin;
        $types = 1;
        $commonCookieName = "cf201507_{$uin}_{$types}";
        $commonCookieValue = 10;

        //检查提交的频率
        $this->checkSubmitFrequency("CF201507_Common", 1);

        //1.通过cookie判断是否领取过礼包
        $isGot = $this->getEventCookie($commonCookieName);
        if (!empty($isGot) && $isGot == $commonCookieValue)
        {
            echo json_encode(array("status" => 0, "msg" => '亲，您已经领取过专属礼包啦！'));
            exit;
        }
        
        //2.通过redis判断是否领取过礼包
        $action = $this->packetArr[$types]["action"];
        $object = $this->packetArr[$types]["object"];
        
        $gotArr = $this->getValuefromRedis($this->eventId, $action, $object, $uin);
        if (!empty($gotArr))
        {
            echo json_encode(array("status" => 0, "msg" => "亲，您已经领取过专属礼包啦！"));
            exit;
        }
        
        //2.判断是否选过大区
        $areaId = intval($this->getAreaIdFromSession($uin));
        if (empty($areaId))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }
        
        //判断是否还有礼包剩余
        $usedTotal = $this->getCounter($this->newPacketRedisKey, $this->newPacketRedisMember);
        if ($usedTotal >= $this->packetArr[$types]["maxNum"])
        {
           $this->emptyPacket();
        }
        
        //3.保存领取礼包数据到redis的列表中，离线发送礼包
        $actionId = $this->packetArr[$types]["actionId"];
        $actionVol = "";
        $actionVal = $areaId;
        $reserve1 = Utils::GetClientIp();
        $reserve2 = "";
        $retArr = $this->addPacketToRedisList($actionId, $uin, $object, $actionVol, $actionVal, $reserve1, $reserve2);
        
        if (!empty($retArr) && $retArr["ret"] == 2)
        {
            //写入数据到cookie和redis中
            $this->setEventCookie($commonCookieName, $commonCookieValue);
            $this->setValueToRedis($this->eventId, $action, $object, $uin, date("Y-m-d H:i:s"));
            //礼包消耗计数器+1
            $this->setCounter($this->newPacketRedisKey, $this->newPacketRedisMember);
            echo '{"status": 1, "msg":"领取礼包成功，24小时内直接发放到绑定的游戏角色！"}';
        }
        else
        {
            $errMsg = '领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744" style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:18px;" target="_blank">【反馈建议】</a>投诉！';
            echo json_encode(array("status"=> 0, "msg"=>$errMsg));
        }
        exit;
    }
    
    //vip礼包
    private function getVipPacket()
    {
        $this->checkEvent();
        $uin = $this->uin;
        $types = 2;
        $vipCookieName = "cf201507_{$uin}_{$types}";
        $vipCookieValue = 20;

        //检查提交的频率
        $this->checkSubmitFrequency("CF201507_VIP", 1);

        //返回值是整型数值，代表vip开通的天数
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);
        $vipTotal = ServiceHelper::Call("event.isOpenVip", $paramsArr);

        if ($vipTotal < (31 * 1))
        {
            echo json_encode(array('status' => -20, 'uin' => $uin, 'msg' => '您还没开通活动VIP!'));
            exit;
        }
        
        //1.通过cookie判断是否领取过礼包
        $isGot = $this->getEventCookie($vipCookieName);
        if (!empty($isGot) && $isGot == $vipCookieValue)
        {
            echo json_encode(array("status" => 0, "msg" => "亲，您已经领取过VIP礼包啦！"));
            exit;
        }
        
        //2.通过redis判断是否领取过礼包
        $action = $this->packetArr[$types]["action"];
        $object = $this->packetArr[$types]["object"];
        
        $gotArr = $this->getValuefromRedis($this->eventId, $action, $object, $uin);
        if (!empty($gotArr))
        {
            echo json_encode(array("status" => 0, "msg" => "亲，您已经领取过VIP礼包啦！"));
            exit;
        }
        
        //2.判断是否选过大区
        $areaId = intval($this->getAreaIdFromSession($uin));
        if (empty($areaId))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }
        
        //判断是否还有礼包剩余
        $usedTotal = $this->getCounter($this->vipPacketRedisKey, $this->vipPacketRedisMember);
        if ($usedTotal >= $this->packetArr[$types]["maxNum"])
        {
           $this->emptyPacket();
        }
        
        //3.保存领取礼包数据到redis的列表中，离线发送礼包
        $actionId = $this->packetArr[$types]["actionId"];
        $actionVol = "";
        $actionVal = $areaId;
        $reserve1 = Utils::GetClientIp();
        $reserve2 = "";
        $retArr = $this->addPacketToRedisList($actionId, $uin, $object, $actionVol, $actionVal, $reserve1, $reserve2);
        
        if (!empty($retArr) && $retArr["ret"] == 2)
        {
            //写入数据到cookie和redis中
            $this->setEventCookie($vipCookieName, $vipCookieValue);
            $this->setValueToRedis($this->eventId, $action, $object, $uin, date("Y-m-d H:i:s"));
            //礼包消耗计数器+1
            $this->setCounter($this->vipPacketRedisKey, $this->vipPacketRedisMember);
            echo '{"status": 1, "msg":"领取礼包成功，24小时内直接发放到绑定的游戏角色！"}';
        }
        else
        {
            $errMsg = '领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744" style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:18px;" target="_blank">【反馈建议】</a>投诉！';
            echo json_encode(array("status"=> 0, "msg"=>$errMsg));
        }
        exit;
    }
    
    //回流礼包
    private function getBackPacket()
    {
        $this->checkEvent();
        $uin = $this->uin;
        $types = 3;
        $backCookieName = "cf201507_{$uin}_{$types}";
        $backCookieValue = 30;

        //检查提交的频率
        $this->checkSubmitFrequency("CF201507_Back", 1);

        //1.通过cookie判断是否领取过礼包
        $isGot = $this->getEventCookie($backCookieName);
        if (!empty($isGot) && $isGot == $backCookieValue)
        {
            echo json_encode(array("status" => 0, "msg" => "亲，您已经领取过回流礼包啦！"));
            exit;
        }
        
        //2.通过redis判断是否领取过礼包
        $action = $this->packetArr[$types]["action"];
        $object = $this->packetArr[$types]["object"];
        
        $gotArr = $this->getValuefromRedis($this->eventId, $action, $object, $uin);
        if (!empty($gotArr))
        {
            echo json_encode(array("status" => 0, "msg" => "亲，您已经领取过回流礼包啦！"));
            exit;
        }
        
        //2.判断是否选过大区
        $areaId = intval($this->getAreaIdFromSession($uin));
        
        if (empty($areaId))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }

        //3.是否是回流玩家
        $level = $this->getUserInfoByCF($uin, $areaId);
        if ($level == 0 || $level == 1)
        {
            echo '{"status":0,"msg":"亲，最后一次登录时间在'.$this->lastLoginDateTime.'之前，才可以领取回流礼包哦~"}';
            exit;
        }
        
        //判断是否还有礼包剩余
        $usedTotal = $this->getCounter($this->oldPacketRedisKey, $this->oldPacketRedisMember);
        if ($usedTotal >= $this->packetArr[$types]["maxNum"])
        {
           $this->emptyPacket();
        }

        //4.保存领取礼包数据到redis的列表中，离线发送礼包
        $actionId = $this->packetArr[$types]["actionId"];
        $actionVol = "";
        $actionVal = $areaId;
        $reserve1 = Utils::GetClientIp();
        $reserve2 = "";
        $retArr = $this->addPacketToRedisList($actionId, $uin, $object, $actionVol, $actionVal, $reserve1, $reserve2);
        
        if (!empty($retArr) && $retArr["ret"] == 2)
        {
            //写入数据到cookie和redis中
            $this->setEventCookie($backCookieName, $backCookieValue);
            $this->setValueToRedis($this->eventId, $action, $object, $uin, date("Y-m-d H:i:s"));
            //礼包消耗计数器+1
            $this->setCounter($this->oldPacketRedisKey, $this->oldPacketRedisMember);
            echo '{"status": 1, "msg":"领取礼包成功，24小时内直接发放到绑定的游戏角色！"}';
        }
        else
        {
            $errMsg = '领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744" style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:18px;" target="_blank">【反馈建议】</a>投诉！';
            echo json_encode(array("status"=> 0, "msg"=>$errMsg));
        }
        exit;
    }
    
    //回流礼包
    private function getNewComerPacket()
    {
        $this->checkEvent();
        $uin = $this->uin;
        $types = 4;
        $newComerCookieName = "cf201507_{$uin}_{$types}";
        $newComerCookieValue = 40;

        //检查提交的频率
        $this->checkSubmitFrequency("CF201507_NewComer", 1);

        //1.通过cookie判断是否领取过礼包
        $isGot = $this->getEventCookie($newComerCookieName);
        if (!empty($isGot) && $isGot == $newComerCookieValue)
        {
            echo json_encode(array("status" => 0, "msg" => "亲，您已经领取过新手专属礼包啦！"));
            exit;
        }
        
        //2.通过redis判断是否领取过礼包
        $action = $this->packetArr[$types]["action"];
        $object = $this->packetArr[$types]["object"];
        
        $gotArr = $this->getValuefromRedis($this->eventId, $action, $object, $uin);
        if (!empty($gotArr))
        {
            echo json_encode(array("status" => 0, "msg" => "亲，您已经领取过新手专属礼包啦！"));
            exit;
        }
        
        //2.判断是否选过大区
        $areaId = intval($this->getAreaIdFromSession($uin));
        
        if (empty($areaId))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }

        //3.是否是回流玩家
        $level = $this->getUserInfoByCF($uin, $areaId);

        if ($level != 3)
        {
            echo '{"status":0,"msg":"亲，新手礼包需要在'.$this->regDateTime.'之后首次注册游戏创建角色的玩家才能领取哦! "}';
            exit;
        }
        
        //判断是否还有礼包剩余
        $usedTotal = $this->getCounter($this->newComerPacketRedisKey, $this->newComerPacketRedisMember);
        if ($usedTotal >= $this->packetArr[$types]["maxNum"])
        {
           $this->emptyPacket();
        }

        //4.保存领取礼包数据到redis的列表中，离线发送礼包
        $actionId = $this->packetArr[$types]["actionId"];
        $actionVol = "";
        $actionVal = $areaId;
        $reserve1 = Utils::GetClientIp();
        $reserve2 = "";
        $retArr = $this->addPacketToRedisList($actionId, $uin, $object, $actionVol, $actionVal, $reserve1, $reserve2);
        
        if (!empty($retArr) && $retArr["ret"] == 2)
        {
            //写入数据到cookie和redis中
            $this->setEventCookie($newComerCookieName, $newComerCookieValue);
            $this->setValueToRedis($this->eventId, $action, $object, $uin, date("Y-m-d H:i:s"));
            //礼包消耗计数器+1
            $this->setCounter($this->newComerPacketRedisKey, $this->newComerPacketRedisMember);
            echo '{"status": 1, "msg":"领取礼包成功，24小时内直接发放到绑定的游戏角色！"}';
        }
        else
        {
            $errMsg = '领取礼包发生错误！请点<a href="http://support.qq.com/write.shtml?fid=744" style="color:#fe8d00;width:111px;display:inline;font-family:microsoft yahei;font-size:18px;" target="_blank">【反馈建议】</a>投诉！';
            echo json_encode(array("status"=> 0, "msg"=>$errMsg));
        }
        exit;
    }

    //计数器+1
    private function setCounter($redisKey, $redisMember)
    {
        $paramsArr = array(
            'key' => $redisKey,
            'member' => $redisMember,
            'terminal' => 10
        );
        ServiceHelper::Call("redis.zincrby", $paramsArr);
    }
    
    /**
     * 获取礼包领取的数量
     */
    private function getCounter($redisKey, $redisMember)
    {
        $paramsArr = array('key'=>$redisKey, 'start'=>'0', 'end'=> -1, 'withScores'=>1, 'terminal'=>10);
        $totalArr = ServiceHelper::Call("redis.zrange", $paramsArr);
        $total = !empty($totalArr[$redisMember]) ? $totalArr[$redisMember] : 0;

        if ($total == 0)
        {
            $paramsArr = array('key'=>$redisKey, 'start'=>'0', 'end'=> -1, 'withScores'=>1, 'terminal'=>10);
            $totalArr = ServiceHelper::Call("redis.zrange", $paramsArr);
            $total = !empty($totalArr[$redisMember]) ? $totalArr[$redisMember] : 0;
        }
        return !empty($total) ? $total : 0;
    }
    
    //将大区id写入到session
    private function addAreaIdToSession($uin, $areaId)
    {
        $sessionName = "CF201507AreaId" . $uin;
        $_SESSION[$sessionName] = $areaId;
        return true;
    }

    //从session中读取大区id
    private function getAreaIdFromSession($uin)
    {
        $sessionName = "CF201507AreaId" . $uin;
        return !empty($_SESSION[$sessionName]) ? $_SESSION[$sessionName] : "";
    }

    //写入数据到redis中
    private function setValueToRedis($eventId, $action, $object, $uin, $value)
    {
        $paramsArr = array(
            'event_id' => $eventId,
            'action' => $action,
            'object' => $object,
            'uin' => $uin,
            'value' => $value
        );
        ServiceHelper::Call("event.addActionUserByRedis", $paramsArr);
    }
    
    //从redis中读取数据
    private function getValuefromRedis($eventId, $action, $object, $uin)
    {
        $paramsArr = array(
            'event_id' => $eventId,
            'action' => $action,
            'object' => $object,
            'uin' => $uin
        );
        $retArr = ServiceHelper::Call("event.getActionUserByRedis", $paramsArr);
        
        if (empty($retArr))
        {
            $retArr = ServiceHelper::Call("event.getActionUserByRedis", $paramsArr);
        }
        
        return $retArr;
    }

    //设置活动的cookie
    private function setEventCookie($cookieName, $cookieValue)
    {
        $cookieValue = base64_encode(urlencode($cookieValue));
        Utils::SetCookie($cookieName, $cookieValue, time()+2592000, "/", "ac.qq.com");
    }

    //获取活动的cookie
    private function getEventCookie($cookieName)
    {
        $cookieValue = Utils::GetCookie($cookieName);
        return !empty($cookieValue) ? urldecode(base64_decode($cookieValue)) : "";
    }

    //领取礼包的数据写入Redis的list
    protected function addPacketToRedisList($actionId, $uin, $object, $actionVol, $actionVal, $reserve1, $reserve2, $terminal = 1)
    {
        $paramsArr = array();
        $paramsArr['action_id'] = $actionId;
        $paramsArr['uin'] = $uin;
        $paramsArr['object'] = $object;
        $paramsArr['terminal'] = $terminal;
        $paramsArr['action_vol'] = $actionVol;
        $paramsArr['action_val'] = $actionVal;
        $paramsArr['reserve1'] = $reserve1;
        $paramsArr['reserve2'] = $reserve2;
        $paramsArr['ctime'] = time();

        $data = array(
            'key' => "ac:event:cf201507:real:list",
            'value' => json_encode($paramsArr),
            'terminal' => 10
        );
        return ServiceHelper::Call("redis.rPush", $data);
    }

    //查看获取的礼包信息
    private function viewGotPacket()
    {
        //验证是否登录
        $this->getLoginInfo();
        $list = array();

        for ($i = 1; $i <= 4; $i++)
        {
            $action = $this->packetArr[$i]["action"];
            $object = $this->packetArr[$i]["object"];

            $date = $this->getValuefromRedis($this->eventId, $action, $object, $this->uin);
            if (!empty($date) && $date != 1)
            {
                $list[] = array("name" => $this->packetArr[$i]["name"], "remark" => "24小时内游戏帐号查询", "date" => $date);
            }
        }

        echo json_encode(array("status"=>1, "list"=>$list));
        exit;
    }

    public function work()
    {
        $action = Utils::GetValue('action');
        switch ($action)
        {
            case 'confirmRole':
                //绑定角色
                $this->confirmRole();
                break;
            case 'getRoleByAreaId':
                //通过区域id获取角色
                $this->getRoleByAreaId();
                break;
            case 'viewPacket':
                $this->viewGotPacket();
                break;
            case 'open_vip':
                //领取VIP礼包
                $this->openVip();
                break;
            case 'getCommonPacket':
                //专属礼包
                $this->getCommonPacket();
                break;
            case 'getVipPacket':
                //领取VIP礼包
                $this->getVipPacket();
                break;
            case 'getBackPacket':
                //回流礼包
                $this->getBackPacket();
                break;
            case 'getNewComerPacket':
                //回流礼包
                $this->getNewComerPacket();
                break;
	    default:
	       break;
        }
    }
}

$event = new Event();
$event->work();
?>