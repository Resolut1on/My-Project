<?php
//ini_set("display_errors",1);
include '../EventModel.class.php';
include '../EventBase.class.php';


class Event extends EventBase
{
    //活动后台配置，用于统计分析
    private $eventId = 1051;
    protected $uin = 0;
    protected $nickname = "";
    //活动变更修改项开始(js文件和礼包发送文件都得修改)
    private $startDateTime = "2015-08-11 00:00:00";
    private $endDateTime = "2015-09-22 23:59:59";
    //活动充值标识
    private $eventAid = "pc_event_qjnn201508";
    //actionId、action、object活动后台配置
    private $packetArr = array(
        1 => array("actionId" => 10195, "name"=>"普通礼包", "mpId"=>"", "action"=>"get", "object" => "common_packet"),
        2 => array("actionId" => 10196, "name"=>"特权礼包", "mpId"=>"", "action"=>"take_packet", "object" => "vip_packet")
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
        
        $value = ServiceHelper::Call("event.getActionUserByRedis", $paramsArr);
        $cdkey = unserialize($value);
        $cdkey = $cdkey['cdkey'];
        
        return !empty($cdkey) ? $cdkey : "";
    }
    
    /*
     * 普通礼包
     */
     protected function getCommonPacket()
    {
        $this->checkEvent();
        
        $packetType = 1;
        $uin = $this->uin;
        $action = $this->packetArr[$packetType]["action"];
        $object = $this->packetArr[$packetType]["object"];
        $mpId = $this->packetArr[$packetType]["mpId"];
        $date = date("Y-m-d H:i:s");
        $clientIp = Utils::GetClientIp();
        $clientIp = !empty($clientIp) ? $clientIp : "10.151.1.229";
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);

             //判断是否已经领取过礼包
            $cdkey = $this->getEventCdkey($uin, $packetType);
//            print_r($cdkey);
            
            if (!empty($cdkey))
            {
               $rs = array('status' => 1, 'msg' => '您已经领取过礼包了!', "cdkey" => $cdkey);
               echo json_encode($rs);exit;   
            }
            else
            {      
//                //一个IP最多2个QQ
//                $uinsArr = $this->getClientIPFromRedis($this->eventId, $action, $object, $clientIp);
//                if (sizeof($uinsArr) >= 2)
//                {
//                    echo json_encode(array("status" => 0, "msg" => "一个IP最多2个QQ能获得CDKey哦！"));
//                    exit;
//                }

                $cdkeyArr = $this->getCdkeyByMp($uin, $mpId, $clientIp);
                $cdkeyArr = array('ret' => 2, 'cdkey' => 'commonPackettest');

                if (!empty($cdkeyArr) && $cdkeyArr["ret"] == 2)
                {   
                    //将用户当天点击的时的IP地址保存到redis
//                    $uinsArr = $this->getClientIPFromRedis($this->eventId, $action, $object, $clientIp);
//                    array_push($uinsArr, $uin);
//                    $uinsArr = array_unique($uinsArr);
//                    $this->saveClientIPToRedis($this->eventId, $action, $object, $clientIp, $uinsArr);

                    $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr['cdkey']);
                    $value = serialize(array('cdkey' => $cdkeyArr["cdkey"], 'dateTime' => $date));
                    $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $value);
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

    //领取VIP礼包
    protected function getVipPacket()
    {
        $this->checkEvent();
        
        $packetType = 2;
        $uin = $this->uin;
        $action = $this->packetArr[$packetType]["action"];
        $object = $this->packetArr[$packetType]["object"];
        $mpId = $this->packetArr[$packetType]["mpId"];
        $date = date("Y-m-d H:i:s");
        $clientIp = Utils::GetClientIp();
        $clientIp = !empty($clientIp) ? $clientIp : "10.151.1.229";
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);

        //返回值是整型数值，代表vip开通的天数
        $vipTotal = ServiceHelper::Call("event.isOpenVip", $paramsArr);
        $vipTotal = 31;
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
            $cdkeyArr = $this->getCdkeyByMp($uin, $mpId, $clientIp);
            $cdkeyArr = array('ret' => 2, 'cdkey' => 'VipPacketTest');

            if (!empty($cdkeyArr) && $cdkeyArr["ret"] == 2)
            {
                $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr['cdkey']);
                $value = serialize(array('cdkey' => $cdkeyArr["cdkey"], 'dateTime' => $date));
                $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $value);
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
    
    //保存用户的同IP地址看漫画的QQ号
    private function saveClientIPToRedis($eventId, $action, $object, $ip, $value)
    {
        $ip = md5($ip);
        $paramsArr = array('event_id' => $eventId,'action' => $action,'object' => $object,'uin' => $ip,'value' => json_encode($value));
        
        return ServiceHelper::Call("event.addActionUserByRedis", $paramsArr);
    }
    
    //获取用户的同IP地址看漫画的QQ号
    private function getClientIPFromRedis($eventId, $action, $object, $ip)
    {
        $ip = md5($ip);
        $paramsArr = array('event_id' => $eventId,'action' => $action,'object' => $object,'uin' => $ip);
        $retArr = ServiceHelper::Call("event.getActionUserByRedis", $paramsArr);
        
        return !empty($retArr) ? json_decode($retArr, true) : array();
    }
    
    //开通vip
    protected function openVip()
    {
        $this->checkEvent();
        echo json_encode(array("status" => 1, "uin" => $this->uin));
        exit;
    }
    
    //查看获取的礼包信息
    private function viewGotPacket()
    {
        //验证是否登录
        $this->getLoginInfo();
        $list = array();

        for ($i = 1; $i <= 2; $i++)
        {
            $action = $this->packetArr[$i]["action"];
            $object = $this->packetArr[$i]["object"];
            
            $value = unserialize($this->getValuefromRedis($this->eventId, $action, $object, $this->uin));
            $cdkey = $value['cdkey'];
            $date = $value['dateTime'];
            if (!empty($date) && $date != 1)
            {
                $list[] = array("cdkey" => $cdkey, "date" => $date);
            }
        }

        echo json_encode(array("status"=>1, "list"=>$list));
        exit;
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

    public function work()
    {
        $action = Utils::GetValue('action');
        switch ($action)
        {
            case 'event_info':
                //登录
                $this->getEventInfo();
                break;   
            case 'getCommonPacket':
                //领取动漫专属福利
                $this->getCommonPacket();
                break;
            case 'getVipPacket':
                //领取VIP礼包
                $this->getVipPacket();
                break;  
            case 'open_vip':
                //领取VIP礼包
                $this->openVip();
                break;
            case 'viewPacket':
                //领取VIP礼包
                $this->viewGotPacket();
                break;
	    default:
                break;
        }
    }
}

$event = new Event();
$event->work();
