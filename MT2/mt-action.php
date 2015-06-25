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
    private $eventId = 1037;
    private $uin = 0;
    private $nickname = "";
    //活动变更修改项开始(js文件和礼包发送文件都得修改)
    private $startDateTime = "2015-06-17 00:00:00";
    private $endDateTime = "2015-07-17 23:59:59";
    //活动充值标识
    private $eventAid = "h5_event_MT220150617";
    private $mpid = "MA20150616161652697";
    //actionId、action、object活动后台配置
    private $packetArr = array("actionId" => 10159, "name"=>"vip礼包", "maxNum" => 45000,"action"=>"get_packet", "object" => "vip_packet");

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
            echo json_encode(array('status' => -95, 'msg' => "活动已经结束，感谢您的关注"));
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
    
    //Service Api2
    private static function API2Service($classFuncName, $paramsArr)
    {
        $v2Client = ServiceHelper::generateV2ServiceClient();
        $retjson = $v2Client->call(array($classFuncName=> $paramsArr));
        
        $retjson = is_string($retjson) ? json_decode($retjson, true) : $retjson;
        return isset($retjson[$classFuncName]) ? $retjson[$classFuncName] : false;
    }
    
    //查看获取的礼包信息(从redis中取)
    private function getEventCdkey($uin)
    {  
        $paramsArr = array('event_id' => $this->eventId,'action' => $this->packetArr["action"],'object' => $this->packetArr["object"],'uin'=>$uin);
        $cdkey = self::API2Service("event.getActionUserByRedis", $paramsArr);
        
        return !empty($cdkey) ? $cdkey : "";
    }

    //领取VIP礼包
    protected function getVipPacket()
    {
        $this->checkEvent();
        
        $uin = $this->uin;
        $action = $this->packetArr["action"];
        $object = $this->packetArr["object"];
        $maxNum = $this->packetArr["maxNum"];
        $date = date("Y-m-d");
        $clientIp = Utils::GetClientIp();
        $clientIp = !empty($clientIp) ? $clientIp : "10.151.1.229";
        $paramsArr = array('uin'=> $uin,'aid'=> $this->eventAid);

        //返回值是礼包领取次数
        $vipPacket = $this->getVipPacketRecord($uin);
        $vipPacket = $vipPacket['count(*)'];//礼包领取次数
//        var_dump($vipPacket);
        //返回值是整型数值，代表vip开通的天数
        $vipTotal = self::API2Service("event.isOpenVip", $paramsArr);
//        $vipTotal = 31;
        if ($vipPacket < 3)
        {
            $cdkeyList = json_decode($this->getEventCdkey($uin));
//            var_dump($cdkeyList);
            
            if ($vipPacket < ($vipTotal / 31)) 
            {
                
                //判断是否还有礼包剩余
                $paramsArr = array('event_id' => $this->eventId, 'action' => $action, 'object' => $object);
                $usedTotal = $this->getEventPacketTotal($paramsArr);
                if ($usedTotal >= $maxNum)
                {
                   echo json_encode(array('status' => -2, 'msg' => '礼包已抢光!'));
                   exit;
                }
                else
                {
                    if ($vipTotal <= (31 * 3)) 
                    {
                        
                        if ($vipTotal >= 31)
                        {
//                            var_dump($cdkeyList->cdkey1);
                            if (empty($cdkeyList->cdkey1))
                            {
                                $cdkeyArr1 = $this->getCdkeyByMp($uin, $this->mpid, $clientIp);
                                if (!empty($cdkeyArr1) && $cdkeyArr1["ret"] == 2)
                                {
                                    $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr1['cdkey']);          
                                }
                                else
                                {
                                    $feedBackMsg = '提示：领取礼包发生错误！';
                                    echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                                    exit;
                                }
                            }
                            else
                            {                              
                                $cdkeyArr1['cdkey'] = $cdkeyList->cdkey1;
//                                var_dump($cdkeyArr1);
                            }
                            
                            if ($vipTotal >= (31 * 2))
                            {   
                                if (empty($cdkeyList->cdkey2))
                                {
                                    $cdkeyArr2 = $this->getCdkeyByMp($uin, $this->mpid, $clientIp);
                                    if (!empty($cdkeyArr2) && $cdkeyArr2["ret"] == 2)
                                    {
                                        $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr2['cdkey']);          
                                    }
                                    else
                                    {
                                        $feedBackMsg = '提示：领取礼包发生错误！';
                                        echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                                        exit;
                                    }
                                }
                                else
                                {
                                    $cdkeyArr2['cdkey'] = $cdkeyList->cdkey2;
                                }
                                
                                if ($vipTotal == (31 * 3))
                                {
                                    $cdkeyArr3 = $this->getCdkeyByMp($uin, $this->mpid, $clientIp);
                                    if (!empty($cdkeyArr3) && $cdkeyArr3["ret"] == 2)
                                    {
                                        $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr3['cdkey']);          
                                    }
                                    else
                                    {
                                        $feedBackMsg = '提示：领取礼包发生错误！';
                                        echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                                        exit;
                                    }
                                }
                            }
                            
                            $cdkeyArrList = json_encode(array('cdkey1' => $cdkeyArr1['cdkey'], 'cdkey2' => $cdkeyArr2['cdkey'], 'cdkey3' => $cdkeyArr3['cdkey']));
                            $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $cdkeyArrList);
                            self::API2Service("event.addActionUserByRedis", $paramsArr);
                            echo json_encode(array("status" => 1, 'cdkey1' => $cdkeyArr1['cdkey'], 'cdkey2' => $cdkeyArr2['cdkey'], 'cdkey3' => $cdkeyArr3['cdkey']));
                            exit;
                            
                        }

                    } 
                    else
                    {
                        if (empty($cdkeyList->cdkey1))
                        {
                            $cdkeyArr1 = $this->getCdkeyByMp($uin, $this->mpid, $clientIp);
                            if (!empty($cdkeyArr1) && $cdkeyArr1["ret"] == 2)
                            {
                                $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr1['cdkey']);
                            }
                            else
                            {
                                $feedBackMsg = '提示：领取礼包发生错误！';
                                echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                                exit;
                            }
                                                           
                        }
                        else
                        {
                            $cdkeyArr1['cdkey'] = $cdkeyList->cdkey1;
                        }
                        
                        if (empty($cdkeyList->cdkey2))
                        {
                            $cdkeyArr2 = $this->getCdkeyByMp($uin, $this->mpid, $clientIp);
                            if (!empty($cdkeyArr2) && $cdkeyArr2["ret"] == 2)
                            {
                                $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr2['cdkey']);
                            }
                            else
                            {
                                $feedBackMsg = '提示：领取礼包发生错误！';
                                echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                                exit;
                            }
                            
                        }
                        else
                        {
                            $cdkeyArr2['cdkey'] = $cdkeyList->cdkey2;
                        }
                        
                        $cdkeyArr3 = $this->getCdkeyByMp($uin, $this->mpid, $clientIp);
                        if (!empty($cdkeyArr3) && $cdkeyArr3["ret"] == 2)
                        {
                            $this->saveSuccessData($this->eventId, $action, $object, $uin, $date, $clientIp, $cdkeyArr3['cdkey']);
                        }
                        else
                        {
                            $feedBackMsg = '提示：领取礼包发生错误！';
                            echo json_encode(array("status" => -3, "msg"=> $feedBackMsg)) ;
                            exit;
                        }
                        
                        
                        $cdkeyArrList = json_encode(array('cdkey1' => $cdkeyArr1['cdkey'], 'cdkey2' => $cdkeyArr2['cdkey'], 'cdkey3' => $cdkeyArr3['cdkey']));
                        $paramsArr = array('event_id' => $this->eventId,'action' => $action,'object' => $object,'uin' => $uin, 'value' => $cdkeyArrList);
                        self::API2Service("event.addActionUserByRedis", $paramsArr);
                        echo json_encode(array("status" => 1, 'cdkey1' => $cdkeyArr1['cdkey'], 'cdkey2' => $cdkeyArr2['cdkey'], 'cdkey3' => $cdkeyArr3['cdkey'], 'vipPacket' => $vipPacket, 'vipTotal' => $vipTotal));
                        exit;
                    }                    
                }
                        
            } 
            else
            {
                echo json_encode(array('status' => -20, 'msg' => '您还没有开通VIP！'));
                exit;
            }
            
        } 
        else
        {
            $cdkeyList = json_decode($this->getEventCdkey($uin));
            echo json_encode(array('status' => 1, 'msg' => '活动礼包限制领取3个', 'cdkey1' => $cdkeyList->cdkey1, 'cdkey2' => $cdkeyList->cdkey2, 'cdkey3' => $cdkeyList->cdkey3));
            exit;
        }
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
        $resArr["isEnd"] = 1;
        //活动进行中
        if (time() < strtotime($this->endDateTime))
        {
            $this->checkLogin();
            $resArr["isEnd"] = 0;
            $resArr["vip_packet"] = json_decode($this->getEventCdkey($this->uin));
            $nickname = !empty($this->nickname) ? $this->nickname : $this->getNick();
            $resArr["nickname"] = $nickname;
        }
        $resArr["status"] = 1;
        echo json_encode($resArr);
        exit;
    }
    
    //获取用户领取礼包次数
    protected function getVipPacketRecord($uin)
    {
        $table = 'tb_event_action_59';
        $sql = "select count(*) from {$table} where uin = {$uin}";
//        var_dump($sql);
        $db = DBHelper::GenerateEventActionClient('slave');
        $row = $db->query($sql);
        if (!empty($row[0]))
            $data = $row[0];
        return $data;
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
            case 'get_packet':
                //领取VIP礼包
                $this->getVipPacket();
                break;
	    default:
	       break;
        }
    }
}

$event = new Event();
$event->work();
