<?php
class EventBase
{
    protected $uin = 0;
    protected $nickname = "";

    public function __construct()
    {
        
    }
    
    /**
     * 生成token
     */
    protected function setToken()
    {
        return ActionHelper::generateToken();
    }

    /**
     * 判读活动是否开始或者结束
     */
    protected function checkEventTime($startDateTime, $endDateTime)
    {
        $time = time();
        if ($time < strtotime($startDateTime))
        {
            echo '{"status":-96, "msg":"活动未开放，请在活动期间参与！"}';
            exit;
        }

        if ($time > strtotime($endDateTime))
        {
            echo '{"status":-95, "msg":"活动已经结束，感谢您的关注"}';
            exit;
        }
    }
    
    /**
     * 判断是否登录
     */
    protected function hasLogin()
    {
        return ($this->uin > 0);
    }
    
    /**
     * 获取登录信息
     */
    protected function getLoginInfo()
    {
        $ptLoginInfo = APIHelper::CheckPtLogin();
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
     * 验证token
     */
    protected function checkToken($tokenKey, $uin)
    {
        if (empty($tokenKey) || $uin != ActionHelper::CheckToken($tokenKey)) 
        {
            echo '{"status": -97, "msg":"token验证错误！"}';
            exit;
        }
    }
    
    /**
    * 检查请求来源
    */
    protected function checkRefer()
    {
        $domain = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        $refer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $referList = parse_url($refer);
        $host = isset($referList['host']) ? $referList['host'] : '';

        $domainFlag = ($domain == 'ac.qq.com');
        $referFlag = ($host == 'ac.qq.com' || $host == 'ac.gtimg.com');

        if (!$domainFlag || !$referFlag)
        {
            echo '{"status": -94, "msg":"非法请求！"}';
            exit;
        }
    }

    /**
     * 判断是否为动漫vip
     */
    protected function isVip($uin)
    {
        $userModel = new UserModel();
        $userInfoArr = $userModel->getUserInfo($uin);
        
        return !empty($userInfoArr) && $userInfoArr[0]["vip_state"] == IS_VIP ? true : false;
    }

    /**
     * 判断是否为动漫年费vip
     */
    protected function isVipYear($uin, $time = 372)
    {
        $uin = intval($uin);
        $isVipYear = false;
        $vipArr = array();
        
        $sql = "select time, create_time from tb_vip_transaction where uin={$uin} and time >= {$time} order by create_time asc";
        $client = DBHelper::GenerateVipClient();
        
        if (!empty($client))
        {
            $vipArr = $client->query($sql);
        }
        
        if (!empty($vipArr))
        {
            foreach ($vipArr as $vip)
            {
                //充值的时间+有效期 > 当前时间
                if (strtotime($vip["create_time"]) + 86400 * intval($vip["time"]) > time())
                {
                    $isVipYear = true;
                    break;
                }
            }
        }
        
        return $isVipYear;
    }

    /**
     * 发放Q币
     * @param type $uin qq号
     * @param type $qbValue qb数量
     * @param type $actionKey 活动的key(需向冲哥申请报备总量，每天限发量)
     */
    protected function payByQB($uin,$qbValue,$actionKey)
    {
        $params = array('uin'=>$uin, 'type'=>'qb', 'value'=>$qbValue, 'actionKey'=>$actionKey);
        $result = ServiceHelper::Call("esales.payByEsales", $params);

        return  $result == 2 ? true : false;
    }

    /**
     * 调用idip接口获取玩家在CF游戏中的角色
     * @param type $cmd 命令字。例如：查询CF玩家资料的命令字是12001。在http://idipauth.ied.com可查询命令字
     * @param type $uin qq号
     * @param type $areaId 玩家所选的区号id
     * 成功返回 array("ret"=>2,"nickName"=>"sanfeng_liu")
     * 失败返回 array("ret"=>1,"errerno"=>9003)
     * 提示:9003代表角色不存在
     */
    //CF
    protected function getRoleByCF($cmd, $uin, $areaId)
    {
        $params = array('cmd'=>$cmd, 'uin'=>$uin, 'area'=>$areaId);
        return ServiceHelper::Call("iegApi.getCfInfo", $params);
    }
    
    /**
     * 御龙在天角色列表
     * @param type $cmd 命令字
     * @param type $uin qq号
     * @param type $areaId 玩家所选的区号id
     * @param type $startId 御龙在天角色列表序号(当有多个角色时，每次获取只会返回2条记录，$startId就是页签的索引)
     */
    protected function getRoleByYlzt($cmd, $uin, $areaId, $startId)
    {
        $params = array('cmd'=>$cmd, 'uin'=>$uin, 'area'=>$areaId, 'startId'=>$startId);
        return ServiceHelper::Call("iegApi.getYlRoleList", $params);
    }
    
    /**
     * 御龙在天角色的注册时间和最后一次登录时间
     * @param type $cmd 命令字
     * @param type $uin 用户QQ
     * @param type $areaId 大区id
     * @param type $characNo 角色号
     */
    protected function getRoleInfoByYlzt($cmd, $uin, $areaId, $characNo)
    {
        $params = array('cmd'=>$cmd, 'uin'=>$uin, 'area'=>$areaId, 'characNo'=>$characNo);
        return ServiceHelper::Call("iegApi.getYlRoleInfo", $params);
    }
    
    /**
     * QQ飞车角色的注册时间和最后一次登录时间,用户角色名
     * @param type $cmd 命令字
     * @param type $uin 用户QQ
     * @param type $areaId 大区id
     */
    protected function getRoleInfoBySpeed($cmd, $uin, $areaId)
    {
        $params = array('cmd'=>$cmd, 'uin'=>$uin, 'area'=>$areaId);
        return ServiceHelper::Call("iegApi.getSpeedInfo", $params);
    }
	
	/**
     * 枪神纪角色的注册时间和最后一次登录时间,用户角色名
     * @param type $cmd 命令字
     * @param type $uin 用户QQ
     * @param type $areaId 大区id
     */
    protected function getRoleInfoByTps($cmd, $uin, $areaId)
    {
        $params = array('cmd'=>$cmd, 'uin'=>$uin, 'area'=>$areaId);
        return ServiceHelper::Call("iegApi.getTpsInfo", $params);
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
    protected function payByCdKey($uin, $mpId, $clientIp)
    {
        $params = array('uin'=>$uin, 'mpId'=>$mpId, 'clientIp'=>$clientIp);
        return ServiceHelper::Call("esales.getCdkeyByMp", $params);
    }
    
    /**
     * 调用mpboss接口发送游戏道具
     * @param type $uin QQ号
     * @param type $mpId 活动营销号
     * @param type $giftName 道具名
     * @param type $zoneId 区域id
     * @param type $roleId 角色id
     * @param type $count 赠送道具数量
     */
    protected function sendPacketByMp($uin, $mpId, $giftName, $zoneId, $roleId, $count = 1)
    {
        $params = array('uin'=>$uin,'mpId'=>$mpId, 'giftName'=>$giftName, 'giftCount'=>$count,'zoneId'=>$zoneId,'roleId'=>$roleId);
        return ServiceHelper::Call("esales.getPropByMp", $params);
    }
    
    /**
     * 取活动的vip充值流水记录或者用户的活动充值流水记录
     * aid是活动在充值流水表的id，用于分辨活动的充值记录
     * 例如：aid=1001|rch=qdqb|44009FBC62994368E2412B1BB6E1FD7624402|
     */
    protected function getVipTransaction($aid, $uin = 0, $num = 0)
    {
        $transArr = array();
        $uin = intval($uin);
        
        $sql = "select * from tb_vip_transaction where remark like '%aid={$aid}%' ";
        if ($uin > 0)
        {
            $sql .= " and uin = {$uin} ";
        }
        
        $sql .= " order by create_time;";
        
        if ($num > 0)
        {
            $sql .= " limit 0,{$num}";
        }
        
        $client = DBHelper::GenerateVipClient();
        if (!empty($client))
        {
            $transArr = $client->query($sql);
        }
        
        return $transArr;
    }
    
    //获取Event活动数据库对象
    protected function getEventClient($serverFlag = 'master')
    {
        $db = "db_dmpt_event";
 
        global $db_dmpt_event_action;
        $config = $db_dmpt_event_action[$serverFlag];
        $post = $serverFlag == 'master' ? 4310 : 4410;
        $dbClient = new DBClient("10.206.30.98", $post, $config['user'], $config['pass']);

        if (!$dbClient->connect())
        {
            return null;
        }

        if (!$dbClient->selectDB($db))
        {
            return null;
        }
            
        return $dbClient;
    }
    
    ///////////////////////////////////////消息提示//////////////////////////////////////////////
    /**
     * 确定支付状态码和消息提示
     */
    protected function gameArea()
    {
        echo '{"status": -80, "msg":"请先绑定游戏的大区角色！~"}';
        exit;
    }
    
    protected function tokenPacket()
    {
        echo '{"status": -81, "msg":"您已领取过的礼包，可以在【获奖信息】查询！~"}';
        exit;
    }
    
    protected function noneVipTrans()
    {
        echo '{"status": -82, "msg":"您没有该活动的VIP充值记录！~"}';
        exit;
    }
    
    protected function emptyPacket()
    {
        echo '{"status": -83, "msg":"礼包已抢光啦，下次活动请早点来捧场哦！~"}';
        exit;
    }

    /**
     * 游戏角色状态码和消息提示
     */
    protected function emptyAreaId()
    {
        echo '{"status": -1, "msg":"大区id不能为空！~"}';
        exit;
    }
    
    protected function emptyAreaName()
    {
        echo '{"status": -2, "msg":"大区名不能为空！~"}';
        exit;
    }
    
    protected function emptyRole()
    {
        echo '{"status": -3, "msg":"角色不能为空！~"}';
        exit;
    }
    
    protected function noneRole()
    {
        echo '{"status": -4, "msg":"很抱歉，在该服务器上未获得角色信息！请检查您是否注册该游戏角色后，再来尝试哦！~"}';
        exit;
    }
    
    /**
     * 抽奖状态码和消息提示
     */
    protected function getQB($num, $isReturn = false)
    {
        if ($isReturn)
        {
            return array("status"=>10, "msg"=>'恭喜您RP大爆发，获得'.$num.'QB！登录<a href="http://pay.qq.com"  style="color:#E8B676;" target="_blank">http://pay.qq.com</a>查询！~');
        }
        else 
        {
            echo '{"status": 10, "msg":"恭喜您RP大爆发，获得'.$num.'QB！登录<a href="http://pay.qq.com"  style="color:#E8B676;" target="_blank">http://pay.qq.com</a>查询！~"}';
            exit;
        }
    }
    
    protected function getMhVip($num, $isReturn = false)
    {
        if ($isReturn)
        {
            return array("status"=>11, "msg"=>"恭喜您RP大爆发，获得了".$num."个月的动漫VIP会员，登录<a href='http://ac.qq.com/home' style='color:#E8B676;' target='_blank'>http://ac.qq.com/home</a>查询！~");
        }
        else
        {
            echo '{"status": 11, "msg":"恭喜您RP大爆发，获得了'.$num.'个月的动漫VIP会员，登录<a href="http://ac.qq.com/home" style="color:#E8B676;" target="_blank">http://ac.qq.com/home</a>查询！~"}';
            exit;
        }
    }
    
    protected function getCdKey($packetName, $cdkey, $linkUrl, $gameName, $isReturn = false)
    {
        if ($isReturn)
        {
            return array("status"=>12, "msg"=>'恭喜您获得：'.$packetName.'。'.$cdkey.'，请复制cdkey，<a href="'.$linkUrl.'" target="_blank">登录'.$gameName.'页面兑换</a>，兑换成功后进入游戏角色查看！~');
        }
        else
        {
            echo '{"status": 12, "msg":"恭喜您获得：'.$packetName.'。'.$cdkey.'，请复制cdkey，<a href="'.$linkUrl.'" target="_blank">登录'.$gameName.'页面兑换</a>，兑换成功后进入游戏角色查看！~"}';
            exit;
        }
    }
    
    protected function getPacket($packetName, $isReturn = false)
    {
        if ($isReturn)
        {
            return array("status"=>13, "msg"=>'恭喜您获得：'.$packetName.'。游戏道具24小时内到账，可直接进入游戏角色中查看！~');
        }
        else
        {
            echo '{"status": 13, "msg":"恭喜您获得：'.$packetName.'。游戏道具24小时内到账，可直接进入游戏角色中查看！~"}';
            exit;
        }
    }
    
    protected function getGameCdKey($packetName, $cdKey, $linkUrl, $isReturn = false)
    {
        if ($isReturn)
        {
            return array("status"=>14, "msg"=>'恭喜您获得：'.$packetName.'。'.$cdKey.'，请复制cdkey，尽快登录'.$linkUrl.'官网激活！~');
        }
        else
        {
            echo '{"status": 14, "msg":"恭喜您获得：'.$packetName.'。'.$cdKey.'，请复制cdkey，尽快登录'.$linkUrl.'官网激活！~"}';
            exit;
        }
    }
    
    protected function getGoods($goodsName, $isReturn = false)
    {
        if ($isReturn)
        {
            return array("status"=>15, "msg"=>'恭喜您RP大爆发，获得'.$goodsName.'大奖！请填写<a href="javascript:void(0);" class="event_address">邮寄地址</a>，活动结束后统一发货！~');
        }
        else
        {
            echo '{"status": 15, "msg":"恭喜您RP大爆发，获得'.$goodsName.'大奖！请填写<a href="javascript:void(0);" class="event_address">邮寄地址</a>，活动结束后统一发货！~"}';
            exit;
        }
    }
    
    protected function getNone()
    {
        echo '{"status": 16, "msg":"差一点点哦，继续攒RP吧！~"}';
        exit;
    }
    
    protected function noneLotteryTimes()
    {
        echo '{"status": 20, "msg":"您有0次抽奖机会。您可以在该页面开通/续费动漫VIP获得抽奖机会！~"}';
        exit;
    }
    
    protected function overLotteryTimes()
    {
        echo '{"status": 21, "msg":"您的抽奖次数已经用完，无法参与抽奖！~"}';
        exit;
    }
}

