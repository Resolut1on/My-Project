<?php
//ini_set("display_errors",1);
include '../EventModel.class.php';
include '../EventBase.class.php';


class Event extends EventBase
{
    //活动后台配置，用于统计分析
    private $eventId = 1045;
    protected $uin = 0;
    protected $nickname = "";
    //活动变更修改项开始(js文件和礼包发送文件都得修改)
    private $startDateTime = "2015-06-26 00:00:00";
    private $endDateTime = "2015-07-26 23:59:59";
    //活动充值标识
    private $eventAid = "pc_event_cf201507";
    //actionId、action、object活动后台配置
    private $packetArr = array(
        1 => array("actionId" => 10161, "name"=>"新手礼包", "mpId"=>"MA20150624175418557", "maxNum" => 400000,"action"=>"get_packet", "object" => "common"),
        2 => array("actionId" => 10162, "name"=>"豪华特权礼包", "mpId"=>"MA20150624175846338", "maxNum" => 100000,"action"=>"take_packet", "object" => "vip_packet")
    );

    public function __construct()
    {   
        
    }
   
    //验证是否登录
    protected function checkLogin() 
    {
        $ptLoginInfo = $this->getLoginInfo();
        if (empty($ptLoginInfo))
        {
            echo '{"status": -99, "msg":"未登录"}';
            exit;
        }

        $uin = isset($ptLoginInfo['Uin']) ? sprintf("%.0f",$ptLoginInfo['Uin']) : 0;
        $nickname = isset($ptLoginInfo['NickName']) ? sprintf("%s",$ptLoginInfo['NickName']) : "";
        if ($uin < 10000)
        {
            echo '{"status": -98, "msg":"QQ不合法"}';
            exit;
        }
        
        $this->uin = $uin;
        $this->nickname = $nickname;
    }
    
    /**
     * 活动验证
     */
    private function checkEvent()
    {
        //验证是否登录
        $this->getLoginInfo();
        
        //验证活动是否开始或者结束
        $this->checkEventTime($this->startDateTime, $this->endDateTime);
        
        //验证token
        $this->checkToken(Utils::GetValue("tokenkey"), $this->uin);
        
        //检查请求来源
        $this->checkRefer();
    }
 
    //活动状态
    protected function checkEventTime($startDateTime, $endDateTime)
    {
        $time = time();
        if ($time < strtotime($startDateTime))
        {
            echo json_encode(array('status' => -96, 'msg' => "活动未开放，请在活动期间参与！"));
            exit;
        }

        if ($time > strtotime($endDateTime))
        {
            echo json_encode(array('status' => -95, 'msg' => "活动已经结束，感谢您的关注！"));
            exit;
        }
    }
    
    //查看获取的礼包信息(从redis中取)
    private function getEventCdkey($uin, $packetType)
    {  
        $paramsArr['uin'] = $uin;
        $paramsArr['event_id'] = $this->eventId;
        $paramsArr['action'] = $this->packetArr[$packetType]['action'];
        $paramsArr['object'] = $this->packetArr[$packetType]['object'];  
        
        $cdkey = ServiceHelper::Call("event.getActionUserByRedis", $paramsArr);
        
        return !empty($cdkey) ? $cdkey : "";
    }

    //领取VIP礼包
    protected function getVipPacket()
    {
        $this->checkEvent();
        
        $packetType = 2;
        $uin = $this->uin;
        $action = $this->packetArr[$packetType]["action"];
        $object = $this->packetArr[$packetType]["object"];
        $maxNum = $this->packetArr[$packetType]["maxNum"];
        $mpId = $this->packetArr[$packetType]["mpId"];
        $date = date("Y-m-d");
        $clientIp = Utils::GetClientIp();
        $clientIp = !empty($clientIp) ? $clientIp : "10.151.1.229";
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);

        //返回值是整型数值，代表vip开通的天数
        $vipTotal = ServiceHelper::Call("event.isOpenVip", $paramsArr);

        if ($vipTotal < (31 * 1))
        {
            echo json_encode(array('status' => -20, 'uin' => $uin, 'msg' => '您还没开通活动VIP!'));
            exit;
        }
        
        //判断是否已经领取过礼包
        $cdkey = $this->getEventCdkey($uin, $packetType);

        if (!empty($cdkey))
        {
           $rs = array('status' => 1, 'msg' => '您已经领取过礼包了!', "cdkey" => $cdkey);
           echo json_encode($rs);exit;   
        }
        else
        {
            //判断是否还有礼包剩余
            $paramsArr = array('event_id' => $this->eventId, 'action' => $action, 'object' => $object);
            $usedTotal = $this->getEventPacketTotal($paramsArr);

            if ($usedTotal >= $maxNum)
            {
               echo json_encode(array('status' => -2, 'msg' => '已经领完'));
               exit;
            }
            else
            {
               $cdkeyArr = $this->getCdkeyByMp($uin, $mpId, $clientIp);

                if (!empty($cdkeyArr) && $cdkeyArr["ret"] == 2)
                {
                    $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr['cdkey']);
                    $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $cdkeyArr["cdkey"]);
                    ServiceHelper::Call("event.addActionUserByRedis", $paramsArr);
                    echo json_encode(array("status" => 1, "cdkey" => $cdkeyArr["cdkey"]));
                    exit;
                }
                else
                {
                    $feedBackMsg = '领取礼包发生错误！';
                    echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                    exit;
                }
            }
        }
    }
    
    protected function getCommonPacket()
    {
        $this->checkEvent();
        
        $packetType = 1;
        $uin = $this->uin;
        $action = $this->packetArr[$packetType]["action"];
        $object = $this->packetArr[$packetType]["object"];
        $maxNum = $this->packetArr[$packetType]["maxNum"];
        $mpId = $this->packetArr[$packetType]["mpId"];
        $date = date("Y-m-d");
        $clientIp = Utils::GetClientIp();
        $clientIp = !empty($clientIp) ? $clientIp : "10.151.1.229";
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);

        //1.判断是否选过大区
        $areaArr = $this->getGameArea($uin);

        if (empty($areaArr))
        {
            //未选择游戏的大区角色
            $this->gameArea();
        }

        $rsArr = $this->getTGameInfo(48002, $uin, $areaArr[0]["area_id"]);
        
        if ($rsArr["ret"] == 2 && $rsArr["crTime"] >= strtotime($this->regDateTime))
        {
            //判断是否已经领取过礼包
            $cdkey = $this->getEventCdkey($uin, $packetType);

            if (!empty($cdkey))
            {
               $rs = array('status' => 1, 'msg' => '您已经领取过礼包了!', "cdkey" => $cdkey);
               echo json_encode($rs);exit;   
            }
            else
            {      
               //判断是否还有礼包剩余
                $paramsArr = array('event_id' => $this->eventId, 'action' => $action, 'object' => $object);
                $usedTotal = $this->getEventPacketTotal($paramsArr);

                if ($usedTotal >= $maxNum)
                {
                   echo json_encode(array('status' => -2, 'msg' => '已经领完'));
                   exit;
                }
                else
                {  

                    $cdkeyArr = $this->getCdkeyByMp($uin, $mpId, $clientIp);

                    if (!empty($cdkeyArr) && $cdkeyArr["ret"] == 2)
                    {   

                        $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr['cdkey']);
                        $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $cdkeyArr["cdkey"]);
                        ServiceHelper::Call("event.addActionUserByRedis", $paramsArr);
                        echo json_encode(array("status" => 1, "cdkey" => $cdkeyArr["cdkey"]));
                        exit;
                    }
                    else
                    {
                        $feedBackMsg = '领取礼包发生错误！';
                        echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                        exit;
                    }
                }
            }
        }
        else
        {
            echo '{"status":0,"msg":"亲，新手礼包需要在'.$this->regDateTime.'之后首次注册游戏创建角色的玩家才能领取哦! "}';
            exit;
        }

           
    }
    
    //保存用户领取CDKEY的数据
    private function saveSuccessData($eventId, $action, $object, $uin, $date, $clientIp, $action_val)
    {
        $paramsArr = array(
            'event_id' => $eventId,
            'action' => $action,
            'object' => $object,
            'uin' => $uin,
            'reserve1' => $date,
            'reserve2' => $clientIp,
            'action_val' => $action_val
        );
        $retArr = ServiceHelper::Call("event.addAction", $paramsArr);
        return $retArr;
    }
    
    /**
     * mpboss平台获得得cdkey方法
     * @param type $uin QQ号
     * @param type $mpId 活动营销号
     * @param type $clientIp 用户的ip
     * 返回值：
     * array(
     *   "ret"=>2,
     *   "msg"=>"XXXXXX",
     *   "cdkey"=>"XXXXXXXXX"
     * )
     */
    protected function getCdkeyByMp($uin, $mpId, $clientIp)
    {
        $params = array('uin'=>$uin, 'mpId'=>$mpId, 'clientIp'=>$clientIp);
        return ServiceHelper::Call("esales.getCdkeyByMp", $params);
    }

    /**
     * 获取礼包领取的数量
     */
    private function getEventPacketTotal($paramsArr)
    {
        $valArr = ServiceHelper::Call("event.getActionStatsByRedis", $paramsArr);
        return !empty($valArr["count"]) ? intval($valArr["count"]) : 0;
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
        
        //验证活动是否开始或者结束
        $this->checkEventTime($this->startDateTime, $this->endDateTime);

        $roleArr = $this->getTGameInfo(48002, $this->uin, $areaId);
        $roleArr["characName"] = iconv("GBK", "UTF-8", urldecode($roleArr["characName"]));
        
        if (!empty($roleArr) && $roleArr["ret"] == 2 && $roleArr["characName"] == $roleName)
        {
            if ($this->addGameArea($this->uin, $areaName, $areaId, '', $roleName))
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
    private function addGameArea($uin, $areaName, $areaId, $roleId, $roleName)
    {
        $uin = intval($uin);
        $areaId = intval($areaId);
        $roleId = mysql_escape_string($roleId);
        $areaName = mysql_escape_string($areaName);
        $roleName = mysql_escape_string($roleName);
        
        $sql = "insert into `tb_event_game_area`(uin,event_aid,area_id,area_name,role_name,ctime) values({$uin},'{$this->eventAid}',{$areaId},'{$areaName}','{$roleName}',now());";
       
        $eventClient = $this->getEventClient();
        return $eventClient->execute($sql);
    }
    
    /**
     * 得到某一用户的大区角色数据
     */
    private function getGameArea($uin)
    {
        $uin = intval($uin);
        $sql = "select area_id, area_name from tb_event_game_area where uin = {$uin} and event_aid = '{$this->eventAid}' limit 1;";
        
        $eventClient = $this->getEventClient("slave");
        return $eventClient->query($sql);
    }

    /**
     * 获取用户的角色
     */
    private function getRoleByAreaId()
    {
        $cmd = 48002;
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

        $roleArr = $this->getTGameInfo($cmd, $this->uin, $areaId);
        $roleArr["characName"] = iconv("GBK", "UTF-8", urldecode($roleArr["characName"]));

        echo json_encode(array("status"=>1,"role"=>$roleArr));
        exit;
    }
    ////////////////////////////////////////////角色区结束////////////////////////////////////////////////
    
    /**
     * 验证是否登录，以及获取用户登录后在活动中的数据
     */
    protected function getEventInfo()
    {
        //默认活动结束
        $resArr['isEnd'] = 1;
        //活动进行中
        if (time() < strtotime($this->endDateTime))
        {
//            $this->getLoginInfo();
            $this->uin = Utils::GetValue('uin');
            $this->nickname = Utils::GetValue('nickname');
            $resArr['isEnd'] = 0;
            $resArr['common_packet'] = $this->getEventCdkey($this->uin, 1);
            $resArr['vip_packet'] = $this->getEventCdkey($this->uin, 2);  
            $resArr['nickname'] = $this->nickname;
        }
        $resArr['status'] = 1;
        echo json_encode($resArr);
        exit;
    }   
    
    //开通vip
    protected function openVip()
    {
        $this->checkEvent();
        echo json_encode(array("status" => 1, "uin" => $this->uin));
        exit;
    }

    public function work()
    {
        $action = Utils::GetValue('action');
        switch ($action)
        {
            case 'event_info':
                //登录
                $this->getEventInfo();
                break;          
            case 'getVipPacket':
                //领取VIP礼包
                $this->getVipPacket();
                break;
            case 'open_vip':
                //领取VIP礼包
                $this->openVip();
                break;
            case 'getCommonPacket':
                //领取动漫专属福利
                $this->getCommonPacket();
                break;
	    default:
	       break;
        }
    }
}

$event = new Event();
$event->work();
