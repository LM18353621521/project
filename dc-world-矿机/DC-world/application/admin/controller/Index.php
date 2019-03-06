<?php
namespace app\admin\controller;
use think\AjaxPage;
use think\Controller;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;
class Index extends Base {

    public function index(){
        $this->pushVersion();
        $act_list = session('act_list');
        $menu_list = getMenuList($act_list);
        $this->assign('menu_list',$menu_list);//view
        $admin_info = session('admin_id') ? getAdminInfo(session('admin_id')) : getSuppliersInfo(session('suppliers_id'));//判断当前登录账号门店Or管理员
        $order_amount = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();

        # 自定义横向导航栏
        $where = array(
            'is_show'=>1,
            'is_select'=>1
        );
        $oneList = M('diyadmin_menu')->where($where)->select();
        $menuList = array();
        foreach ($oneList as $key => $value) {
            if (!empty($value['eng_name']) && $value['pid'] ==0 && $value['is_select'] ==1) { // 有英文名字 为一级菜单
                $menuList[$value['eng_name']] = $value['name'];
            }
        }
        $this->assign('menuList',$menuList);
        # END

        $this->assign('order_amount',$order_amount);
        $this->assign('admin_info',$admin_info);
        $this->assign('menu',getMenuArr());   //view2
        $this->assign('menu',getMenuArrNew());   //view2
        return $this->fetch();
    }

    public function welcome(){
    	$this->assign('sys_info',$this->get_sys_info());
    	$today = strtotime("Today",time());

        $yesterday  = $today-86400;

        //昨日注册会员
        $where_m_y =array(
            'createTime'=>array('between',array(date('Y-m-d H:i:s',$yesterday),date('Y-m-d H:i:s',$today))),
            'isDelete'=>2
        );
        $count['yesterday_member'] = M('member')->where($where_m_y)->count();
        //现有注册
        $where_m_all =array(
            'isDelete'=>2
        );
        $count['all_member'] = M('member')->where($where_m_all)->count();

        //昨日释放DC
        $where_m_y =array(
            'type'=>array('in',[3,9]),
            'change_time'=>array('between',array($yesterday,$today)),
        );
        $count['yesterday_release'] = M('world_dc_log')->where($where_m_y)->count();
        //已释放DC
        $where_m_y =array(
            'type'=>array('in',[3,9]),
        );
        $count['all_release'] = M('world_dc_log')->where($where_m_y)->count();

        //昨日新增矿机数量
        $minerList = db('world_miner')->where(array('type' => 1, 'is_on_sale' => 1, 'is_del' => 0))->order('miner_price')->field('miner_id,miner_name,short_name')->select();

        foreach ($minerList as &$value) {
            $where_miner_y= array(
                'miner_id' => $value['miner_id'],
                'add_time'=>array('between',array($yesterday,$today)),
                'source_type'=>1,
            );
            $yesterday_miner = db('world_users_miner')
                ->where($where_miner_y)
                ->count();
            $value['miner_num'] = $yesterday_miner;
            $where_miner_y= array(
                'miner_id' => $value['miner_id'],
                'add_time'=>array('between',array($yesterday,$today)),
                'source_type'=>3,
            );
            $yesterday_miner_s = db('world_users_miner')
                ->where($where_miner_y)
                ->count();
            $value['miner_num_s']  = $yesterday_miner_s;
        }

        $where_miner_all= array(
            'source_type'=>array('in',[1,3]),
            'status'=>1,
        );
        $count['miner_all'] = db('world_users_miner')
            ->where($where_miner_all)
            ->count();

    	$today = strtotime(date("Y-m-d"));
//    	$count['handle_order'] = M('order')->where("order_status=0 and (pay_status=1 or pay_code='cod')")->count();//待处理订单
//    	$count['new_order'] = M('order')->where("add_time>=$today")->count();//今天新增订单
//    	$count['goods'] =  M('goods')->where("1=1")->count();//商品总数
//    	$count['article'] =  M('article')->where("1=1")->count();//文章总数
//    	$count['users'] = M('users')->where("1=1")->count();//会员总数
//    	$count['today_login'] = M('users')->where("last_login>=$today")->count();//今日访问
//    	$count['new_users'] = M('users')->where("reg_time>=$today")->count();//新增会员
//    	$count['comment'] = M('comment')->where("is_show=0")->count();//最新评论
    	$this->assign('count',$count);
        $this->assign('minerList',$minerList);
        return $this->fetch();
    }

    public function get_sys_info(){
		$sys_info['os']             = PHP_OS;
		$sys_info['zlib']           = function_exists('gzclose') ? 'YES' : 'NO';//zlib
		$sys_info['safe_mode']      = (boolean) ini_get('safe_mode') ? 'YES' : 'NO';//safe_mode = Off
		$sys_info['timezone']       = function_exists("date_default_timezone_get") ? date_default_timezone_get() : "no_timezone";
		$sys_info['curl']			= function_exists('curl_init') ? 'YES' : 'NO';
		$sys_info['web_server']     = $_SERVER['SERVER_SOFTWARE'];
		$sys_info['phpv']           = phpversion();
		$sys_info['ip'] 			= GetHostByName($_SERVER['SERVER_NAME']);
		$sys_info['fileupload']     = @ini_get('file_uploads') ? ini_get('upload_max_filesize') :'unknown';
		$sys_info['max_ex_time'] 	= @ini_get("max_execution_time").'s'; //脚本最大执行时间
		$sys_info['set_time_limit'] = function_exists("set_time_limit") ? true : false;
		$sys_info['domain'] 		= $_SERVER['HTTP_HOST'];
		$sys_info['memory_limit']   = ini_get('memory_limit');
        $sys_info['version']   	    = file_get_contents(APP_PATH.'admin/conf/version.php');
		$mysqlinfo = Db::query("SELECT VERSION() as version");
		$sys_info['mysql_version']  = $mysqlinfo[0]['version'];
		if(function_exists("gd_info")){
			$gd = gd_info();
			$sys_info['gdinfo'] 	= $gd['GD Version'];
		}else {
			$sys_info['gdinfo'] 	= "未知";
		}
		return $sys_info;
    }

    // 在线升级系统
    public function pushVersion()
    {
        if(!empty($_SESSION['isset_push']))
            return false;
        $_SESSION['isset_push'] = 1;
        error_reporting(0);//关闭所有错误报告
        $app_path = dirname($_SERVER['SCRIPT_FILENAME']).'/';
        $version_txt_path = $app_path.'/application/admin/conf/version.php';
        $curent_version = file_get_contents($version_txt_path);

        $vaules = array(
                'domain'=>$_SERVER['SERVER_NAME'],
                'last_domain'=>$_SERVER['SERVER_NAME'],
                'key_num'=>$curent_version,
                'install_time'=>INSTALL_DATE,
                'serial_number'=>SERIALNUMBER,
         );
         $url = "http://service.tp-shop.cn/index.php?m=Home&c=Index&a=user_push&".http_build_query($vaules);
         stream_context_set_default(array('http' => array('timeout' => 3)));
         file_get_contents($url);
    }

    /**
     * ajax 修改指定表数据字段  一般修改状态 比如 是否推荐 是否开启 等 图标切换的
     * table,id_name,id_value,field,value
     */
    public function changeTableVal(){
            $table = I('table'); // 表名
            $id_name = I('id_name'); // 表主键id名
            $id_value = I('id_value'); // 表主键id值
            $field  = I('field'); // 修改哪个字段
            $value  = I('value'); // 修改字段值
            M($table)->where("$id_name = $id_value")->save(array($field=>$value)); // 根据条件保存修改的数据
    }

    public function about(){
    	return $this->fetch();
    }
}