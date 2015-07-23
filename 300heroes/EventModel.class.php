<?php

/**
 * 活动操作类
 * @author towwen
 */
define('APP_PATH', dirname(__FILE__) . '/../../');
define('ACPHP_PATH', APP_PATH . '/Framework/');
define('LIB_PATH', APP_PATH);
include_once(APP_PATH . 'Conf/app.config.php');
include_once(ACPHP_PATH . "ACPHP.class.php");

class EventModel {

	
    public function __construct() {
        $this->redis_terminal = 10;
        $this->stats_category = array('view', 'login', 'click', 'link', 'stats');
    }

    /**
     * 新增活动行为记录(直接写库)
     * @param array
     * @return array
     */
    public function addAction($params) {

        $item = array();
        //兼容版本
        if (isset($params['action_tag']) && isset($params['event_id'])) {
            $event_id = isset($params['event_id']) ? intval($params['event_id']) : 0;
            $action_tag = isset($params['action_tag']) ? trim($params['action_tag']) : '';
            $key = "conf:action:{$event_id}:{$action_tag}";
            $data = array(
                'key' => $key,
                'terminal' => $this->redis_terminal
            );
            $val = ServiceHelper::Call("redis.get", $data);
            $ret = json_decode($val[$key], TRUE);
            if (!empty($ret) && $ret['id'] > 0 && !in_array($ret['category'], $this->stats_category)) {
                $item['action_id'] = $ret['id'];
            }
        } else {
            $item['action_id'] = isset($params['action_id']) ? intval($params['action_id']) : 0;
        }

        $item['uin'] = isset($params['uin']) ? intval($params['uin']) : 0;
        $item['object'] = isset($params['object']) ? Utils::EscapeDBInput(($params['object'])) : '';
        $item['action_vol'] = isset($params['action_vol']) ? intval($params['action_vol']) : 0;
        $item['action_val'] = isset($params['action_val']) ? Utils::EscapeDBInput(($params['action_val'])) : '';
        $item['terminal'] = isset($params['terminal']) ? intval(($params['terminal'])) : 1;
        $item['ctime'] = isset($params['ctime']) ? intval($params['ctime']) : time();
        $item['state'] = isset($params['state']) ? intval($params['state']) : 1;
        $item['reserve1'] = isset($params['reserve1']) ? Utils::EscapeDBInput($params['reserve1']) : '';
        $item['reserve2'] = isset($params['reserve2']) ? Utils::EscapeDBInput($params['reserve2']) : '';

        $data = array('status' => 1, 'id' => 0);
        if ($item['action_id'] > 0) {
            $db = DBHelper::GenerateEventActionClient('master');
            $table = 'tb_event_action_' . intval($item['action_id'] % 100);

            if (!empty($item)) {
                $cols = '';
                foreach ($item as $k => $v)
                    $cols .= "`{$k}` = '{$v}',";

                $cols = trim($cols, ',');
                $sql = "insert into `{$table}` set {$cols}";
                //file_put_contents("/data/website/ac/Runtime/Logs/".date("Ymd")."/EventModel_sql.log", date("Y-m-d H:i:s") . " " .$sql.chr(10).chr(10), FILE_APPEND);
                if ($db->execute($sql)) {
                    $data['status'] = 2;
                    $data['id'] = $db->getLastInsertId();
                    
                    //计数(临时)
                    if ($event_id == 1013 && $action_tag == 'click_packet' && in_array($item['object'], array('new_packet', 'old_packet')))
                    {
	                    $key = "stats:{$event_id}:{$action_tag}:{$item['object']}";
			            $params_redis = array(
			                'key' => $key,
			                'terminal' => $this->redis_terminal
			            );
			            $val = ServiceHelper::Call("redis.get", $params_redis);
			            $ret = json_decode($val[$key], TRUE);
			            $total = abs(intval($ret)) + 1;
			            
			            $params_redis = array(
			                'key' => $key,
			            	'value' => $total,
			                'terminal' => $this->redis_terminal
			            );
			            ServiceHelper::Call("redis.set", $params_redis);
                    }
                }
            }
        }
        return $data;
    }
    


    /**
     * 访问点击统计
     * @param array
     * @return array
     */
    public function addStats($params) {
        $event_id = isset($params['event_id']) ? intval($params['event_id']) : 0;
        $action_tag = isset($params['action_tag']) ? trim($params['action_tag']) : '';
        $key = "conf:action:{$event_id}:{$action_tag}";
        $data = array(
            'key' => $key,
            'terminal' => $this->redis_terminal
        );
        $val = ServiceHelper::Call("redis.get", $data);
        $ret = json_decode($val[$key], TRUE);
        if (!empty($ret) && $ret['id'] > 0 && in_array($ret['category'], $this->stats_category)) {
            $item = array();
            $item['action_id'] = $ret['id'];
            $item['uin'] = isset($params['uin']) ? intval($params['uin']) : 0;
            $item['object'] = isset($params['object']) ? Utils::EscapeDBInput(($params['object'])) : '';
            $item['terminal'] = isset($params['terminal']) ? intval(($params['terminal'])) : 1;
            $item['ctime'] = time();

            $data = array(
                'key' => "stats:list:real",
                'value' => json_encode($item),
                'terminal' => $this->redis_terminal
            );
            ServiceHelper::Call("redis.rPush", $data);
        }
    }

    /**
     * 检查用户行为是否已存在
     * @param int action_id
     * @param int uin
     * @return int id
     */
    public function checkActionExist($action_id, $object, $uin) {
        $action_id = intval($action_id);
        $uin = intval($uin);
        if ($action_id > 0 && $uin > 0) {
            $db = DBHelper::GenerateEventActionClient('master');
            $table = 'tb_event_action_' . intval($action_id % 100);
            $where = "`state` = 1 and `action_id` = {$action_id} and `uin` = {$uin}";
            $where .= empty($object) ? '' : " and `object` = '{$object}'";
            $sql = "select `id` from {$table} where {$where} limit 1";
            $row = $db->query($sql);
            if (!empty($row[0]))
                return $row[0]['id'];
        }
        return 0;
    }

    /**
     * 获得行为统计数据
     * @param int $action_id
     * @param string $object
     * @return array
     */
    public function getActionStats($params) {
    	
        $event_id = isset($params['event_id']) ? intval($params['event_id']) : 0;
        $action_tag = isset($params['action_tag']) ? Utils::EscapeDBInput($params['action_tag']) : '';
		$object  = isset($params['object']) ? Utils::EscapeDBInput($params['object']) : '';
		
		$start = isset($params['start']) ? intval($params['start']) : 0;
		$end = isset($params['end']) ? intval($params['end']) : 0;

        $action_id = 0;
        $data = array();
        if ($event_id > 0 && !empty($action_tag)) {
            $ret = $this->getActionInfo($event_id, $action_tag);
            if (!empty($ret) && $ret['id'] > 0)
                $action_id = $ret['id'];
        }
        
        $data = array('count' => 0, 'user' => 0, 'vol' => 0);
        if ($action_id > 0) {
            $db = DBHelper::GenerateEventActionClient('slave');
            $table = 'tb_event_action_' . intval($action_id % 100);
            $where = "`state` = 1 and `action_id` = {$action_id}";
            $where .= empty($object) ? '' : " and `object` = '{$object}'";
            $where .= $start > 0 ? " and `ctime` >= {$start}" : '';
            $where .= $end > 0 ? " and `ctime` < {$end}" : '';
             
            $sql = "select count(`id`) as `count`, count(distinct `uin`) as `user`, sum(`action_vol`) as `vol` from {$table} where {$where}";
            $row = $db->query($sql);
            if (!empty($row[0]))
                $data = $row[0];
        }
        return $data;
    }

    /**
     * 查询行为记录(仅一条)
     * @param array
     * @return array
     */
    public function getAction($params) {
        $event_id = isset($params['event_id']) ? intval($params['event_id']) : 0;
        $action_tag = isset($params['action_tag']) ? trim($params['action_tag']) : '';

        $action_id = 0;
        $data = array();
        if ($event_id > 0 && !empty($action_tag)) {
            $ret = $this->getActionInfo($event_id, $action_tag);
            if (!empty($ret) && $ret['id'] > 0 && !in_array($ret['category'], $this->stats_category))
                $action_id = $ret['id'];
        }

        $uin = isset($params['uin']) ? intval($params['uin']) : 0;
        $object = isset($params['object']) ? Utils::EscapeDBInput(($params['object'])) : '';
        $action_vol = isset($params['action_vol']) ? intval($params['action_vol']) : -1;
        $action_val = isset($params['action_val']) ? Utils::EscapeDBInput(($params['action_val'])) : '';
        $terminal = isset($params['terminal']) ? intval(($params['terminal'])) : 0;
        $state = isset($params['state']) ? intval($params['state']) : 1;
        $reserve1 = isset($params['reserve1']) ? Utils::EscapeDBInput($params['reserve1']) : '';
        $reserve2 = isset($params['reserve2']) ? Utils::EscapeDBInput($params['reserve2']) : '';

        if ($action_id > 0 && $uin > 0) {
            $db = DBHelper::GenerateEventActionClient('master');
            $table = 'tb_event_action_' . intval($action_id % 100);
            $where = "`action_id` = {$action_id} and `uin` = {$uin}";
            $where .= empty($object) ? '' : " and `object` = '{$object}'";
            $where .= $action_vol > 0 ? " and `action_vol` = {$action_vol}" : '';
            $where .= empty($action_val) ? '' : " and `action_val` = '{$action_val}'";
            $where .= $terminal > 0 ? " and `terminal` = {$terminal}" : '';
            $where .= $state > 0 ? " and `state` = {$state}" : '';
            $where .= empty($reserve1) ? '' : " and `reserve1` = '{$reserve1}'";
            $where .= empty($reserve2) ? '' : " and `reserve2` = '{$reserve2}'";
            $sql = "select * from {$table} where {$where} limit 1";
            $row = $db->query($sql);
            if (!empty($row[0]))
                $data = $row[0];
        }
        return $data;
    }

    /**
     * 获得行为列表
     * @param array
     * @return array
     */
    public function getActionList($params) {
        $event_id = isset($params['event_id']) ? intval($params['event_id']) : 0;
        $action_tag = isset($params['action_tag']) ? trim($params['action_tag']) : '';

        $action_id = 0;
        $data = array();
        if ($event_id > 0 && !empty($action_tag)) {
            $ret = $this->getActionInfo($event_id, $action_tag);
            if (!empty($ret) && $ret['id'] > 0 && !in_array($ret['category'], $this->stats_category))
                $action_id = $ret['id'];
        }

        $uin = isset($params['uin']) ? intval($params['uin']) : 0;
        $object = isset($params['object']) ? Utils::EscapeDBInput(($params['object'])) : '';
        $action_vol = isset($params['action_vol']) ? intval($params['action_vol']) : -1;
        $action_val = isset($params['action_val']) ? Utils::EscapeDBInput(($params['action_val'])) : '';
        $terminal = isset($params['terminal']) ? intval(($params['terminal'])) : 0;
        $state = isset($params['state']) ? intval($params['state']) : 1;
        $reserve1 = isset($params['reserve1']) ? Utils::EscapeDBInput($params['reserve1']) : '';
        $reserve2 = isset($params['reserve2']) ? Utils::EscapeDBInput($params['reserve2']) : '';

        $page = isset($params['page']) ? intval($params['page']) : 1;
        $page_size = isset($params['page_size']) ? intval($params['page_size']) : 10;
        $sort = isset($params['sort']) ? Utils::EscapeDBInput(($params['sort'])) : '';
        $order = isset($params['order']) && $params['order'] == 'asc' ? 'asc' : 'desc';
        $start = ($page - 1) * $page_size;

        $data = array('total' => 0, 'data' => array());
        if ($action_id > 0) {
            $db = DBHelper::GenerateEventActionClient('slave');
            $table = 'tb_event_action_' . intval($action_id % 100);
            $where = "`action_id` = {$action_id}";
            //判断私有或公开
            $where .= intval($ret['privacy']) == 1 ? " and `uin` = {$uin}": ($uin > 0 ? " and `uin` = {$uin}" : ''); 
            
            $where .= empty($object) ? '' : " and `object` = '{$object}'";
            $where .= $action_vol >= 0 ? " and `action_vol` = {$action_vol}" : '';
            $where .= empty($action_val) ? '' : " and `action_val` = '{$action_val}'";
            $where .= $terminal > 0 ? " and `terminal` = {$terminal}" : '';
            $where .= $state > 0 ? " and `state` = {$state}" : '';
            $where .= empty($reserve1) ? '' : " and `reserve1` = '{$reserve1}'";
            $where .= empty($reserve2) ? '' : " and `reserve2` = '{$reserve2}'";
            $where .= empty($sort) ? '' : " order by `{$sort}` {$order}";
            $sql_count = "select count(`id`) as total from {$table} where {$where}";
           
            $row = $db->query($sql_count);
            if (!empty($row[0]) && intval($row[0]['total']) > 0) {
                $data['total'] = intval($row[0]['total']);
                $sql = "select * from {$table} where {$where} limit {$start}, {$page_size}";
                $rows = $db->query($sql);
                
                if (!empty($rows))
                {
                	$objectInfo = json_decode($ret['objects'], TRUE);
                	foreach ($rows as $k=>$row)
                	{
                		$uinfo = $this->getUserInfo($row['uin']);
                    	$rows[$k]['nickname'] = !empty($uinfo['nick']) ? $uinfo['nick'] : $row['uin'];
                    	$rows[$k]['object'] = isset($objectInfo[$row['object']]) ? $objectInfo[$row['object']] : $row['object'];
                	}
                	$data['data'] = $rows;
                }
            }
        }
        return $data;
    }

    /**
     * 更新行为记录（仅一条）
     * @param array
     * @return array
     */
    public function updateAction($params) {
        $id = isset($params['id']) ? intval($params['id']) : 0;
        $item = array();
        $item['action_id'] = isset($params['action_id']) ? intval($params['action_id']) : 0;
        $item['uin'] = isset($params['uin']) ? intval($params['uin']) : 0;
        $item['object'] = isset($params['object']) ? Utils::EscapeDBInput(($params['object'])) : '';
        $item['action_vol'] = isset($params['action_vol']) ? intval($params['action_vol']) : 0;
        $item['action_val'] = isset($params['action_val']) ? Utils::EscapeDBInput(($params['action_val'])) : '';
        $item['terminal'] = isset($params['terminal']) ? intval(($params['terminal'])) : 1;
        $item['ctime'] = isset($params['ctime']) ? intval($params['ctime']) : time();
        $item['state'] = isset($params['state']) ? intval($params['state']) : 1;
        $item['reserve1'] = isset($params['reserve1']) ? Utils::EscapeDBInput($params['reserve1']) : '';
        $item['reserve2'] = isset($params['reserve2']) ? Utils::EscapeDBInput($params['reserve2']) : '';

        $data = array('status' => 1, 'id' => 0);
        if ($id > 0) {
            $db = DBHelper::GenerateEventActionClient('master');
            $table = 'tb_event_action_' . intval($action_id % 100);

            $cols = '';
            if (!empty($item)) {
                foreach ($item as $k => $v)
                    $cols = "`{$k}` = '{$v}',";
            }
            $cols = trim($cols, ',');
            $sql = "update {$table} set {$cols} where `id` = {$id} limit 1";
            if ($db->execute($sql))
                $data = array('status' => 2, 'id' => $id);
        }
        return $data;
    }

    /**
     * 获得活动基本信息
     * @param int $event_id
     * @return array
     */
    public function getEventInfo($event_id) {
        $key = 'conf:event:' . intval($event_id);
        $data = array();
        if ($event_id > 0) {
            $params = array(
                'key' => $key,
                'terminal' => $this->redis_terminal
            );
            $ret = ServiceHelper::Call("redis.get", $params);
            $data = json_decode($ret[$key], TRUE);
        }
        return $data;
    }

    public function getActionInfo($event_id, $action) {
        $key = "conf:action:{$event_id}:{$action}";
        $data = array();
        if ($event_id > 0 && !empty($action)) {
            $params = array(
                'key' => $key,
                'terminal' => $this->redis_terminal
            );
            $ret = ServiceHelper::Call("redis.get", $params);
            if (!empty($ret[$key]))
                $data = json_decode($ret[$key], TRUE);
        }
        return $data;
    }
    
    public function isOpenEventVip($aid, $uin)
    {
        $sql = "select uin from tb_vip_transaction where remark like '%aid={$aid}%' and uin = {$uin} limit 1;";
        
        $client = DBHelper::GenerateVipClient();
        $transArr = $client->query($sql);
        
        return !empty($transArr) ? 1 : 0;
    }

    //活动用户信息
    public function getUserInfo($uin)
    {
        	$params = array();
        	$params['uin'] = $uin;
        	$params['fields'] = "nick";
        	
        	return ServiceHelper::Call("user.getUserInfo", $params);
        	
     }

     /**
      * 日漫接口
      */
     public function getActionListAc($params) {
        $event_id = isset($params['event_id']) ? intval($params['event_id']) : 0;
        $action_tag = isset($params['action_tag']) ? trim($params['action_tag']) : '';

        $action_id = 0;
        $data = array();
        if ($event_id > 0 && !empty($action_tag)) {
            $ret = $this->getActionInfo($event_id, $action_tag);
           
            if (!empty($ret) && $ret['id'] > 0 && !in_array($ret['category'], $this->stats_category))
                $action_id = $ret['id'];
        }
        
        //浏览数（参与人）
        $item = array();
        $item['event_id'] = $event_id;
        $item['action_tag'] = 'view';
        $stats = $this->getActionStats($item);
        $view = isset($stats['count']) ? intval($stats['count']): 0;
      
        $uin = isset($params['uin']) ? intval($params['uin']) : 0;
        $object = isset($params['object']) ? Utils::EscapeDBInput(($params['object'])) : '';
        $action_vol = isset($params['action_vol']) ? intval($params['action_vol']) : -1;
        $action_val = isset($params['action_val']) ? Utils::EscapeDBInput(($params['action_val'])) : '';
        $terminal = isset($params['terminal']) ? intval(($params['terminal'])) : 0;
        $state = isset($params['state']) ? intval($params['state']) : 1;
        $reserve1 = isset($params['reserve1']) ? Utils::EscapeDBInput($params['reserve1']) : '';
        $reserve2 = isset($params['reserve2']) ? Utils::EscapeDBInput($params['reserve2']) : '';

        $page = isset($params['page']) ? intval($params['page']) : 1;
        $page_size = isset($params['page_size']) ? intval($params['page_size']) : 10;
        $sort = isset($params['sort']) ? Utils::EscapeDBInput(($params['sort'])) : 'id';
        $order = isset($params['order']) && $params['order'] == 'asc' ? 'asc' : 'desc';
        $start = ($page - 1) * $page_size;

        $data = array('total' => 0, 'view' => $view, 'data' => array());
        if ($action_id > 0) {
            $db = DBHelper::GenerateEventActionClient('slave');
            $table = 'tb_event_action_' . intval($action_id % 100);
            $where = "`action_id` = {$action_id}";
            $where .= intval($ret['privacy']) == 1 ? " and `uin` = {$uin}": ''; //判断私有或公开
            $where .= empty($object) ? '' : " and `object` = '{$object}'";
            $where .= $action_vol >= 0 ? " and `action_vol` = {$action_vol}" : '';
            $where .= empty($action_val) ? '' : " and `action_val` = '{$action_val}'";
            $where .= $terminal > 0 ? " and `terminal` = {$terminal}" : '';
            $where .= $state > 0 ? " and `state` = {$state}" : '';
            $where .= empty($reserve1) ? '' : " and `reserve1` = '{$reserve1}'";
            $where .= empty($reserve2) ? '' : " and `reserve2` = '{$reserve2}'";
            
            $sql_count = "select count(`id`) as total from {$table} where {$where}";
            $row = $db->query($sql_count);
            if (!empty($row[0]) && intval($row[0]['total']) > 0) {
                $data['total'] = intval($row[0]['total']);
                $col = isset($params['col']) ? $params['col'] : 'object';
                $where .= " group by `uin`";
                $where .= empty($sort) ? '' : " order by `{$sort}` {$order}";
                $sql = "select `uin`,{$col} as `col`  from {$table} where {$where} limit {$start}, {$page_size} ";
                $rows = $db->query($sql);
                if (!empty($rows))
                {
                	if ($col == 'object')
                		$objectInfo = json_decode($ret['objects'], TRUE);
                	foreach ($rows as $k=>$row)
                	{
                		$uinfo = $this->getUserInfo($row['uin']);
                		$data['data'][$k]['uin'] = $row['uin'];
                    	$data['data'][$k]['nickname'] = !empty($uinfo['nick']) ? $uinfo['nick'] : $row['uin'];
                    	$data['data'][$k]['col'] = $col == 'object' ? $objectInfo[$row['col']] : $row['col'];
                	}
                }
            }
        }
        return $data;
    }
    
    /**
     * 检验VIP开通数
     */
	public function	getVipStats($aid)
	{
		$aid = !empty($aid) ? Utils::EscapeDBInput($aid) : '';
		$data = array('user' => 0);
    	if (!empty($aid))
    	{
    		$db = DBHelper::GenerateVipClient('slave');
	        $sql = "select count(distinct `uin`) as `user` from tb_vip_transaction where remark like '%aid={$aid}%';";
	        $row = $db->query($sql);
	        if (!empty($row))
	        	$data['user'] = intval($row[0]['user']);
    	}
        return $data;
	}
	
	public function checkEventVip($aid, $uin)
	{
		$data = array('ret'=>0);
		$aid = !empty($aid) ? Utils::EscapeDBInput($aid) : '';
    	$uin = intval($uin);
    	if (!empty($aid))
    	{
    		sleep(1);
	        $db = DBHelper::GenerateVipClient('slave');
	        $where = "`source_tag` = '{$aid}'";
	        $where .= $uin > 0 ? " and `uin` = {$uin}" : '';
	        $sql = "select count(*) as `times` from tb_vip_transaction where {$where};";
	        $row = $db->query($sql);
	        if (!empty($row))
	        	$data['ret'] = intval($row[0]['times']);
    	}
        return $data;
	}
     
}

?>