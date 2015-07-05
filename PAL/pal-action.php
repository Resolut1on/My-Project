<?php
//ini_set("display_errors",1);
define('APP_PATH', dirname(__FILE__) . '/../../../');
define('ACPHP_PATH', APP_PATH . '/Libs/Framework/');
define('LIB_PATH', APP_PATH);
include_once(APP_PATH . 'Conf/app.config.php');
include_once(ACPHP_PATH . "ACPHP.class.php");
include_once(APP_PATH . 'Common/Utils.class.php');

class Event
{
    //活动后台配置，用于统计分析
    private $eventId = 1033;
    private $uin = 0;
    private $nickname = "";
    //活动变更修改项开始(js文件和礼包发送文件都得修改)
    private $startDateTime = "2015-06-04 00:00:00";
    private $endDateTime = "2015-06-05 23:59:59";
    //活动充值标识
    private $eventAid = "h5_event_pal201506";
//    private $mpid = "";
    //actionId、action、object活动后台配置
    private $packetArr = array(
        1 => array("actionId" => 10146, "name"=>"动漫VIP礼包", "mpId"=>"", "maxNum" => 50000,"action"=>"take_packet", "object" => "vip_packet"),
        2 => array("actionId" => 10145, "name"=>"动漫专属福利", "mpId"=>"", "maxNum" => 50000,"action"=>"get_packet", "object" => "common")
    );

    public function __construct()
    {
        
    }
    
    private function getNick()
    {
        $pUin = Utils::GetCookie('p_uin');
        $pUin = intval($pUin);
        $ret = UserHelper::getUserInfo($pUin);
        if (!empty($ret))
        {
            return $ret[$pUin]["nick_name"];
        }
        else
        {
            return "腾讯网友";
        }
    }

    private function CheckPtLogin()
    {
        $pSkey = Utils::GetCookie('p_skey');
        $pUin = Utils::GetCookie('p_uin');
        if (empty($pSkey) || empty($pUin))
        {
            return false;
        }
        $pUin = str_replace("o","", $pUin);
        $pUin = intval($pUin);
        $res = Utils::HttpGet("http://10.217.105.97/api/ptlogin?uin={$pUin}&pskey={$pSkey}");
        $ret = json_decode($res, true);
        if(isset($ret['ret']) && $ret['ret'] == 0 && isset($ret['Uin']) )
        {
            return $ret;
        }
        else
        {
            return false;
        }
    }

    //验证是否登录
    protected function checkLogin() 
    {
        $ptLoginInfo = $this->CheckPtLogin();
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
        $this->checkLogin();
        
        //验证活动是否开始或者结束
        $this->checkEventTime($this->startDateTime, $this->endDateTime);
    }
    
    //活动状态
    private function checkEventTime($startDateTime, $endDateTime)
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
      
    //Service Api2
    private static function API2Service($classFuncName, $paramsArr)
    {
        $v2Client = ServiceHelper::generateV2ServiceClient();
        $retjson = $v2Client->call(array($classFuncName=> $paramsArr));
        
        $retjson = is_string($retjson) ? json_decode($retjson, true) : $retjson;
        return isset($retjson[$classFuncName]) ? $retjson[$classFuncName] : false;
    }
    
    //查看获取的礼包信息(从redis中取)
    private function getEventCdkey($uin, $packetType)
    {  
        $paramsArr['uin'] = $uin;
        $paramsArr['event_id'] = $this->eventId;
        $paramsArr['action'] = $this->packetArr[$packetType]['action'];
        $paramsArr['object'] = $this->packetArr[$packetType]['object'];  
        
        $cdkey = self::API2Service("event.getActionUserByRedis", $paramsArr);
        
        return !empty($cdkey) ? $cdkey : "";
    }

    //领取VIP礼包
    protected function getVipPacket()
    {
        $this->checkEvent();
        
        $packetType = 1;
        $uin = $this->uin;
        $action = $this->packetArr[$packetType]["action"];
        $object = $this->packetArr[$packetType]["object"];
        $maxNum = $this->packetArr[$packetType]["maxNum"];
        $date = date("Y-m-d");
        $clientIp = Utils::GetClientIp();
        $clientIp = !empty($clientIp) ? $clientIp : "10.151.1.229";
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);

        //返回值是整型数值，代表vip开通的天数
        $vipTotal = self::API2Service("event.isOpenVip", $paramsArr);
//        $vipTotal = 400;
        if ($vipTotal < (31 * 1))
        {
            echo json_encode(array('status' => -20, 'uin' => $uin, 'msg' => '您还没开通活动VIP!'));
            exit;
        }
        
        //判断是否已经领取过礼包
         $cdkey = $this->getEventCdkey($uin, $packetType);
//         $cdkey = "testforxianjian";
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
//             $usedTotal = 50000;
             if ($usedTotal >= $maxNum)
             {
                echo json_encode(array('status' => -2, 'msg' => '礼包已抢光!'));
                exit;
             }
             else
             {
                $cdkeyArr = $this->getCdkeyByDmpt($uin, $this->eventAid, 1, $clientIp);

                if (!empty($cdkeyArr) && $cdkeyArr["ret"] == 2)
                {
                    $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $cdkeyArr["cdkey"]);
                    self::API2Service("event.addActionUserByRedis", $paramsArr);
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
    
    protected function getComicPacket()
    {
        $this->checkEvent();
        
        $packetType = 2;
        $uin = $this->uin;
        $action = $this->packetArr[$packetType]["action"];
        $object = $this->packetArr[$packetType]["object"];
        $maxNum = $this->packetArr[$packetType]["maxNum"];
        $date = date("Y-m-d");
        $clientIp = Utils::GetClientIp();
        $clientIp = !empty($clientIp) ? $clientIp : "10.151.1.229";
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);
     
        //判断是否已经领取过礼包
         $cdkey = $this->getEventCdkey($uin, $packetType);
//         $cdkey = "testforcomicpacket";
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
//             $usedTotal = 50000;
             if ($usedTotal >= $maxNum)
             {
                echo json_encode(array('status' => -2, 'msg' => '礼包已抢光!'));
                exit;
             }
             else
             {
                //一个IP最多2个QQ
                $uinsArr = $this->getClientIPFromRedis($this->eventId, $action, $object, $clientIp);
                if (sizeof($uinsArr) >= 2)
                {
                    echo json_encode(array("status" => 0, "msg" => "一个IP最多2个QQ能获得CDKey哦！"));
                    exit;
                }
            
                $cdkeyArr = $this->getCdkeyByDmpt($uin, $this->eventAid, 2, $clientIp);
//                $cdkeyArr = array("ret" => 2, "cdkey" => "CDKey1234567890");

                if (!empty($cdkeyArr) && $cdkeyArr["ret"] == 2)
                {
                    //将用户当天点击的时的IP地址保存到redis
                    $uinsArr = $this->getClientIPFromRedis($this->eventId, $action, $object, $clientIp);
                    array_push($uinsArr, $uin);
                    $uinsArr = array_unique($uinsArr);
                    $this->saveClientIPToRedis($this->eventId, $action, $object, $clientIp, $uinsArr);
                    
                    $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $cdkeyArr["cdkey"]);
                    self::API2Service("event.addActionUserByRedis", $paramsArr);
                    echo json_encode(array("status" => 1, "cdkey" => $cdkeyArr["cdkey"]));
                    exit;
                }
                else if (!empty($cdkeyArr) && $cdkeyArr["ret"] == 3) 
                {
                    echo json_encode(array("status" => -100, "msg" => "访问受限！"));
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
     
    //调用动漫平台接口获取cdkey
    private function getCdkeyByDmpt($uin, $eventAid, $type, $clientIp)
    {
        $paramsArr = array("uin" => $uin, "eventId" => $eventAid, "type" => $type, "clientIp" => $clientIp);
        return self::API2Service("esales.getCdkeyByDmpt", $paramsArr);
    }

    /**
     * 获取礼包领取的数量
     */
    private function getEventPacketTotal($paramsArr)
    {
        $valArr = self::API2Service("event.getActionStatsByRedis", $paramsArr);
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
            $this->checkLogin();
            $resArr['isEnd'] = 0;
            $resArr['vip_packet'] = $this->getEventCdkey($this->uin, 1);
            $resArr['comic_packet'] = $this->getEventCdkey($this->uin, 2);
            $nickname = !empty($this->nickname) ? $this->nickname : $this->getNick();
            $resArr['nickname'] = $nickname;
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
    
    //保存用户的同IP地址看漫画的QQ号
    private function saveClientIPToRedis($eventId, $action, $object, $ip, $value)
    {
        $ip = md5($ip);
        $paramsArr = array('event_id' => $eventId,'action' => $action,'object' => $object,'uin' => $ip,'value' => json_encode($value));
        
        return self::API2Service("event.addActionUserByRedis", $paramsArr);
//        return ServiceHelper::Call("event.addActionUserByRedis", $paramsArr);
    }
    
    //获取用户的同IP地址看漫画的QQ号
    private function getClientIPFromRedis($eventId, $action, $object, $ip)
    {
        $ip = md5($ip);
        $paramsArr = array('event_id' => $eventId,'action' => $action,'object' => $object,'uin' => $ip);
        $retArr = self::API2Service("event.getActionUserByRedis", $paramsArr);
        
        return !empty($retArr) ? json_decode($retArr, true) : array();
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
            case 'getComicPacket':
                //领取动漫专属福利
                $this->getComicPacket();
                break;
            case 'open_vip':
                $this->openVip();
                break;
	    default:
	       break;
        }
    }
}

$event = new Event();
$event->work();
