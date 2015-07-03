<?php
date_default_timezone_set("Asia/Shanghai");
include_once dirname(__FILE__) . '/EventModel.class.php';

$page = new Event();
$page->work();

class Event {

    private $uin = 0;
    private $redis_terminal = 10;
 
    public function __construct() 
    {
    	$this->stats_category = array('login', 'stats');
    }

    public function work() 
    {
        $action_list = array(
            'check_login', 					//检查登录
        	'get_event_info', 				//获得活动信息
            'check_event_state', 			//检查活动时间
        	'get_vip_stats',				//获得活动开通VIP的次数、人数及金额
        	'is_vip',
        	'is_open_vip',					//校验用户是否已开通VIP
        	'is_open_vip_by_openid',
            'get_action_stats_by_redis', 	//通过redis查询行为次数及量值
        	'get_action_stats_by_mysql', 	//通过mysql查询行为次数、用户数及量值
        	'add_action', 					//新增一条记录
        	'get_action', 					//获取一条记录
            'get_action_list', 				//获得记录列表  
            'update_action_by_id', 			//更新一条记录
        	'add_action_user_by_redis',		//新增行为用户
        	'get_action_user_by_redis',		//获得行为用户域，亦可校验是否已参与
        	'get_action_user_stats_by_redis',//获得行为用户数(已去重)
            'stats',     					//访问点击数据采集
        	'get_action_list_ac'			//日漫频道接口
        );

        $action = strtolower(Utils::EscapeDBInput(Utils::GetValue('action')));
        if (in_array($action, $action_list))
            $this->$action();
    }

    private function add_action() 
    {
        $ret = $this->check_login('array');
        if ($ret['status'] == -1)
        {
            $this->return_format($ret);
            exit();
        }
        $event_id = intval(Utils::GetValue('event_id'));
        $action_tag = isset($_POST['action_tag']) ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
        $ret = array('status' => 0, 'msg' => '非法请求', 'id' => '0');
        if ($event_id > 0 && $this->uin > 0 && !empty($action_tag)) 
        {
            $item = array();
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['uin'] = $this->uin;
            $item['object'] = isset($_POST['object']) ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            $item['action_val'] = isset($_POST['action_val']) ? Utils::EscapeDBInput(Utils::GetValue('action_val')) : '';
            $item['action_vol'] = isset($_POST['action_vol']) ? intval(Utils::GetValue('action_vol')) : 0;
            $item['terminal'] = isset($_POST['terminal']) ? intval(Utils::GetValue('terminal')) : 1;
            $item['ctime'] = isset($_POST['ctime']) ? intval(Utils::GetValue('ctime')) : time();
            $item['state'] = isset($_POST['state']) ? intval(Utils::GetValue('state')) : 1;
            $item['reserve1'] = isset($_POST['reserve1']) ? Utils::EscapeDBInput(Utils::GetValue('reserve1')) : '';
            $item['reserve2'] = isset($_POST['reserve2']) ? Utils::EscapeDBInput(Utils::GetValue('reserve2')) : '';
            //$item['unique'] = isset($_POST['unique']) ? intval(Utils::GetValue('unique')) : 0;
            
			$res = ServiceHelper::Call("event.addAction", $item);
            if ($res['status'] == 2)
                $ret = array('status' => 2, 'msg' => '保存成功', 'id' => $res['id']);
            else
                $ret = array('status' => 1, 'msg' => '保存失败', 'id' => '0');
        }
        $this->return_format($ret);
    }

    private function get_action() 
    {
        $ret = $this->check_login('array');
        if ($ret['status'] == -1)
        {
            $this->return_format($ret);
            exit();
        }
        $event_id = intval(Utils::GetValue('event_id'));
        $action_tag = $this->isset_pg('action_tag') ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
        $ret = array('status' => 1, 'msg' => '未查到记录', 'data' => array());
        if ($event_id > 0 && $this->uin > 0) {
            $item = array();
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['uin'] = $this->uin;
            $item['object'] = $this->isset_pg('object') ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            $item['action_val'] = $this->isset_pg('action_val') ? Utils::EscapeDBInput(Utils::GetValue('action_val')) : '';
            $item['action_vol'] = $this->isset_pg('action_vol') ? intval(Utils::GetValue('action_vol')) : -1;
            $item['terminal'] = $this->isset_pg('terminal') ? intval(Utils::GetValue('terminal')) : 0;
            $item['state'] = $this->isset_pg('state') ? intval(Utils::GetValue('state')) : 1;
            $item['reserve1'] = $this->isset_pg('reserve1') ? Utils::EscapeDBInput(Utils::GetValue('reserve1')) : '';
            $item['reserve2'] = $this->isset_pg('reserve2') ? Utils::EscapeDBInput(Utils::GetValue('reserve2')) : '';

            $data = ServiceHelper::Call("event.getAction", $item);
            if (!empty($data))
                $ret = array('status' => 2, 'msg' => 'ok', 'data' => $data);
        }
        $this->return_format($ret);
    }
    
    private function update_action_by_id()
    {
        $ret = $this->check_login('array');
        if ($ret['status'] == -1)
        {
            $this->return_format($ret);
            exit();
        }
        $id = intval(Utils::GetValue('id'));
        $event_id = intval(Utils::GetValue('event_id'));
        $action_tag = isset($_POST['action_tag']) ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : 'test';
        $ret = array('status' => 0, 'msg' => '非法请求', 'id' => '0');
        if ($event_id > 0 && !empty($action_tag) && $id > 0) 
        {
            $item = array();
            $item['id'] = $id;
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['uin'] = isset($_POST['uin']) && is_numeric($_POST['uin']) ? Utils::GetValue('uin') : $this->uin;
            $item['object'] = isset($_POST['object']) ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            $item['action_val'] = isset($_POST['action_val']) ? Utils::EscapeDBInput(Utils::GetValue('action_val')) : '';
            $item['action_vol'] = isset($_POST['action_vol']) ? intval(Utils::GetValue('action_vol')) : 0;
            $item['terminal'] = isset($_POST['terminal']) ? intval(Utils::GetValue('terminal')) : 1;
            $item['ctime'] = isset($_POST['ctime']) ? intval(Utils::GetValue('ctime')) : time();
            $item['state'] = isset($_POST['state']) ? intval(Utils::GetValue('state')) : 1;
            $item['reserve1'] = isset($_POST['reserve1']) ? Utils::EscapeDBInput(Utils::GetValue('reserve1')) : '';
            $item['reserve2'] = isset($_POST['reserve2']) ? Utils::EscapeDBInput(Utils::GetValue('reserve2')) : '';
            //$item['unique'] = isset($_POST['unique']) ? intval(Utils::GetValue('unique')) : 0;
            
			$res = ServiceHelper::Call("event.updateActionById", $item);
            if ($res['status'] == 2)
                $ret = array('status' => 2, 'msg' => '保存成功', 'id' => $res['id']);
            else
                $ret = array('status' => 1, 'msg' => '保存失败', 'id' => '0');
        }
        $this->return_format($ret);    	
    }

    private function get_action_list() 
    {
        $event_id = intval(Utils::GetValue('event_id'));
        $action_tag = $this->isset_pg('action_tag') ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
        $ret = array('status' => 1, 'msg' => '请求参数非法');
        if ($event_id > 0 && !empty($action_tag)) 
        {
            $item = array();
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['uin'] = $this->isset_pg('uin') ? intval(Utils::GetValue('uin')) : 0;
            $item['object'] = $this->isset_pg('object') ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            $item['action_val'] = $this->isset_pg('action_val') ? Utils::EscapeDBInput(Utils::GetValue('action_val')) : '';
            $item['action_vol'] = $this->isset_pg('action_vol') ? intval(Utils::GetValue('action_vol')) : -1;
            $item['terminal'] = $this->isset_pg('terminal') ? intval(Utils::GetValue('terminal')) : 0;
            $item['state'] = $this->isset_pg('state') ? intval(Utils::GetValue('state')) : 1;
            $item['reserve1'] = $this->isset_pg('reserve1') ? Utils::EscapeDBInput(Utils::GetValue('reserve1')) : '';
            $item['reserve2'] = $this->isset_pg('reserve2') ? Utils::EscapeDBInput(Utils::GetValue('reserve2')) : '';

            $item['page'] = $this->isset_pg('page') ? intval(Utils::GetValue('page')) : 1;
        	$item['page_size'] = $this->isset_pg('page_size') ? intval(Utils::GetValue('page_size')) : 10;
        	$item['sort'] = $this->isset_pg('sort') ? Utils::EscapeDBInput(($params['sort'])) : '';
			$item['order'] = $this->isset_pg('order') && Utils::GetValue('order') == 'asc' ? 'asc' : 'desc';

            $data = ServiceHelper::Call("event.getActionList", $item);
            if (!empty($data))
                $ret = array('status' => 2, 'msg' => 'ok', 'total' => intval($data['total']), 'data' => $data['data']);
        }
		$this->return_format($ret);
    }

    private function get_action_list_ac() 
    {
        $event_id = intval(Utils::GetValue('event_id'));
        $action_tag = $this->isset_pg('action_tag') ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
        $res = array('status' => 1, 'msg' => '请求参数非法');
        if ($event_id > 0 && !empty($action_tag)) 
        {
            $item = array();
            $item['event_id'] = $event_id;
            $item['action_tag'] = $action_tag;
            $item['uin'] = $this->isset_pg('uin') ? intval(Utils::GetValue('uin')) : 0;
            $item['object'] = $this->isset_pg('object') ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            $item['action_val'] = $this->isset_pg('action_val') ? Utils::EscapeDBInput(Utils::GetValue('action_val')) : '';
            $item['action_vol'] = $this->isset_pg('action_vol') ? intval(Utils::GetValue('action_vol')) : -1;
            $item['terminal'] = $this->isset_pg('terminal') ? intval(Utils::GetValue('terminal')) : 0;
            $item['state'] = $this->isset_pg('state') ? intval(Utils::GetValue('state')) : 1;
            $item['reserve1'] = $this->isset_pg('reserve1') ? Utils::EscapeDBInput(Utils::GetValue('reserve1')) : '';
            $item['reserve2'] = $this->isset_pg('reserve2') ? Utils::EscapeDBInput(Utils::GetValue('reserve2')) : '';
            
			$item['col'] = $this->isset_pg('col') ? Utils::EscapeDBInput(Utils::GetValue('col')) : 'object';

            $item['page'] = $this->isset_pg('page') ? intval(Utils::GetValue('page')) : 1;
        	$item['page_size'] = $this->isset_pg('page_size') ? intval(Utils::GetValue('page_size')) : 10;
        	$item['sort'] = $this->isset_pg('sort') ? Utils::EscapeDBInput(($params['sort'])) : 'id';
			$item['order'] = $this->isset_pg('order') && Utils::GetValue('order') == 'asc' ? 'asc' : 'desc';
		
            $this->EventModel = new EventModel();
            $data = $this->EventModel->getActionListAc($item);

            if (!empty($data))
                $res = array('status' => 2, 'msg' => 'ok', 'view'=> intval($data['view']), 'total' => intval($data['total']), 'data' => $data['data']);
        }
        $callback = $this->isset_pg('callback') ? Utils::EscapeDBInput(Utils::GetValue('callback')) : '';
        if (empty($callback))
    		exit(json_encode($res));
    	else 
    		exit($callback.'('.json_encode($res).')');
    }

    private function add_action_user_by_redis()
    {
        $ret = $this->check_login('array');
        if ($ret['status'] == -1)
        {
            $this->return_format($ret);
            exit();
        }
        
    	$event_id = intval(Utils::GetValue('event_id'));
    	$action_tag = $this->isset_pg('action_tag') ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
    	
    	$ret = array('status' => 1, 'msg' => '新增失败');
    	if ($event_id > 0 && !empty($action_tag) && $this->uin > 0) 
    	{
    		$item = array();
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['object'] = $this->isset_pg('object') ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            $item['uin'] = $this->uin;
            $item['value'] = $action_tag;

            $res = ServiceHelper::Call("event.addActionUserByRedis", $item);	
            if ($res == 1)
            	$ret = array('status' => 2, 'msg' => '新增成功');				
    	}
		 $this->return_format($ret);    	
    }
    
    private function get_action_user_by_redis()
    {
    	$event_id = intval(Utils::GetValue('event_id'));
    	$action_tag = $this->isset_pg('action_tag') ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
    	
    	$data = array('count' => 0, 'vol' => 0);
    	if ($event_id > 0 && !empty($action_tag)) 
    	{
    		$item = array();
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['object'] = $this->isset_pg('object') ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            
            $data = ServiceHelper::Call("event.getActionUserByRedis", $item);					
    	}
		 $this->return_format($data);	
    }
    
    private function get_action_stats_by_redis()
    {
    	$event_id = intval(Utils::GetValue('event_id'));
    	$action_tag = $this->isset_pg('action_tag') ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
    	
    	$data = array('count' => 0, 'vol' => 0);
    	if ($event_id > 0 && !empty($action_tag)) 
    	{
    		$item = array();
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['object'] = $this->isset_pg('object') ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            
            $data = ServiceHelper::Call("event.getActionStatsByRedis", $item);					
    	}
		 $this->return_format($data);	
    }
    
    private function get_action_stats_by_mysql()
    {
    	$event_id = intval(Utils::GetValue('event_id'));
    	$action_tag = $this->isset_pg('action_tag') ? Utils::EscapeDBInput(Utils::GetValue('action_tag')) : '';
    	
    	$data = array('count' => 0, 'vol' => 0);
    	if ($event_id > 0 && !empty($action_tag)) 
    	{
    		$item = array();
            $item['event_id'] = $event_id;
            $item['action'] = $action_tag;
            $item['object'] = $this->isset_pg('object') ? Utils::EscapeDBInput(Utils::GetValue('object')) : '';
            
            $data = ServiceHelper::Call("event.getActionStatsByMysql", $item);					
    	}
		 $this->return_format($data);	
    }
    
    private function get_vip_stats()
    {
    	$aid = $this->isset_pg('aid') ? Utils::EscapeDBInput(Utils::GetValue('aid')) : '';
    	if (!empty($aid))
    	{
	    	$params = array(
				'aid' => $aid
			);
	        $data = ServiceHelper::Call("event.getVipStats", $params);		
	        $this->return_format($data);	
    	}
    }
    
    private function is_vip()
    {
        $params = array(
        	'uin' => is_numeric(Utils::GetValue("uin")) ? Utils::GetValue("uin") : 0
		);
		$ret = ServiceHelper::Call("user.checkVipUser", $params);	
	    $this->return_format($ret);	    	
    }
    
    private function is_open_vip()
    {
        $params = array(
			'aid' => $this->isset_pg('aid') ? Utils::EscapeDBInput(Utils::GetValue('aid')) : '',
        	'uin' => is_numeric(Utils::GetValue("uin")) ? Utils::GetValue("uin") : 0
		);
		$ret = ServiceHelper::Call("event.isOpenVip", $params);	
	    $this->return_format($ret);	
    }
    
    private function is_open_vip_by_openid()
    {
    	$aid = $this->isset_pg('aid') ? Utils::EscapeDBInput(Utils::GetValue('aid')) : '';
    	$appid = $this->isset_pg('appid') ? intval(Utils::GetValue('appid')): 0;
    	$openid = $this->isset_pg('openid') ? trim(Utils::GetValue('openid')) : '';
    	$openkey = $this->isset_pg('openkey') ? trim(Utils::GetValue('openkey')) : '';
    	$uin = 0;
    	
    	if (!empty($aid) && $appid > 0 && !empty($openid) && !empty($openkey))
    	{
    		$params = array(
				'appId' => $appid,
	        	'openId' => $openid,
    			'openKey' => $openkey
			);
			$ret = ServiceHelper::Call("iegApi.checkOpenId", $params);	
			$uin = isset($ret['uin']) && is_numeric($ret['uin']) ? $ret['uin'] : 0;
    	}
        $params = array(
			'aid' => $aid,
        	'uin' => $uin
		);
		$ret = ServiceHelper::Call("event.isOpenVip", $params);	
	    $this->return_format($ret);	
    }
    
    private function get_event_info() 
    {
    	$ret = array('status' => 1, 'msg' => '活动不存在', 'data' => array());
        $params = array(
			'event_id' => intval(Utils::GetValue('event_id'))
		);
        $data = ServiceHelper::Call("event.getEventInfo", $params);
        if (!empty($data))
            $ret = array('status' => 2, 'msg' => '正常', 'data' => $data);
            
        $this->return_format($ret);
    }
    
    private function check_event_state($type = 'json') 
    {
        $event_id = intval(Utils::GetValue('event_id'));
        $params = array(
			'event_id' => intval(Utils::GetValue('event_id'))
		);
        $data = ServiceHelper::Call("event.getEventInfo", $params);
        $ret = array('status' => 1, 'msg' => '活动不存在');
        if (!empty($data)) 
        {
            $time = time();
            $ret = array('status' => 1, 'msg' => '正常');
            if ($time > $data['end']) 
            {
                $ret['status'] = -1;
                $ret['msg'] = "活动已经结束，谢谢参与！";
            } 
            else if ($time < $data['start']) 
            {
                $ret['status'] = -1;
                $ret['msg'] = "活动还未开始，请耐心等待。";
            }
        }
        if ($type == 'array')
        	return $ret;
        else 
        	$this->return_format($ret);
    }

    private function stats() 
    {
        $ret = $this->check_referer();
        if ($ret['status'] == -1)
            exit(json_encode($ret));
        
        $stats = Utils::EscapeDBInput(Utils::GetValue('stats'));
        $arr = explode('.', $stats);
		$event_id = intval(Utils::GetValue('event_id'));
        $action_tag = isset($arr[0]) ? trim($arr[0]) : '';
        $object = isset($arr[1]) ? trim($arr[1]) : '';
            
        $action_id = 0;
        $category = '';
        if ($event_id > 0 && !empty($action_tag)) 
	    {
			$key = "conf:action:{$event_id}:{$action_tag}";
			$params = array(
				'key' => $key,
				'terminal' => $this->redis_terminal
			);
			$json = ServiceHelper::Call("redis.get", $params);
	        $ret = json_decode($json[$key], TRUE);
	        $action_id = isset($ret['id']) ? intval($ret['id']) : 0;
	        $category = isset($ret['category']) ? trim($ret['category']) : '';
		}
		
		$res = array();
        if ($action_id > 0 && in_array($category, $this->stats_category)) 
        {
            $item = array();
            $item['action_id'] = $action_id;
            $item['uin'] = intval(Utils::GetValue('uin')) > 0 ? intval(Utils::GetValue('uin')) : intval(str_replace('o', '', Utils::GetCookie('uin')));
            $item['object'] = $object;
            $item['terminal'] = intval(Utils::GetValue('terminal')) > 0 ? intval(Utils::GetValue('terminal')) : 1;
            $item['ctime'] = time();

            $data = array(
                'key' => "stats:list:real",
                'value' => json_encode($item),
                'terminal' => $this->redis_terminal
            );
            $res = ServiceHelper::Call("redis.rPush", $data); 
        }     
        $this->return_format($res); 
    }

    private function check_login($type = 'json') 
    {
        $ptLoginInfo = APIHelper::CheckPtLogin();
        $this->uin = isset($ptLoginInfo['Uin']) ? sprintf('%.0f', $ptLoginInfo['Uin']) : 0;
        
        $ret = array('status' => 1, 'msg' => '已登录', 'uin' => $this->uin);
        if ($this->uin < 10000) 
        {
            $ret['status'] = -1;
            $ret['msg'] = "未登录";
            $ret['uin'] = 0;
        }
        if ($type == 'array')
        	return $ret;
       	else 
        	$this->return_format($ret);
    }

    private function check_referer() 
    {
        $domain = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        $refer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $referList = parse_url($refer);
        $host = isset($referList['host']) ? $referList['host'] : '';

        $white_list = array(
            'ac.qq.com' => 1,
            'm.ac.qq.com' => 1,
            '3g.ac.qq.com' => 1,
            'ac.gtimg.com' => 2
        );

        $ret = array('status' => -1, 'msg' => "非法请求");
        if (isset($white_list[$domain]) && isset($white_list[$host]))
            $ret = array('status' => 1, 'msg' => "合法请求");

        return $ret;
    }

    private function isset_pg($param) 
    {
        return isset($_POST[$param]) || isset($_GET[$param]) ? TRUE : FALSE;
    }
    
    private function return_format($data)
    {
    	$callback = $this->isset_pg('callback') ? Utils::EscapeDBInput(Utils::GetValue('callback')) : '';
    	$type = !empty($callback) ? 'jsonp' : 'json';
		switch ($type)
		{
			case 'json':
				exit(json_encode($data));
				break;
			case 'jsonp':
				exit($callback.'('.json_encode($data).')');
				break;
		}	
    }
    
}

?>