<?php

namespace app\admin\controller\User;

use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use \app\common\model\Users;
use think\Loader;
use app\admin\controller\Base;
use app\common\logic\QualificationLogic;

class User extends Base {

    /*
     * 配置入口
     */
    public function shop_index()
    {
        /*配置列表*/
        $group_list = [
            'shop_info' => '消费专区',
            'shop_proxy'  => '代理专区',
            'shop_integral' => '积分商城',
        ];
        $inc_type =  I('get.inc_type','shop_info');
        $where['o.user_id']=I('user_id');
        $where['o.pay_status']=1;
        if($inc_type=='shop_integral'){
            $where['o.type']=2;
        }elseif($inc_type=='shop_proxy'){
            $where['o.type']=0;
        }else{
            $where['o.type']=1;
        }
        $p = I('p')?:1;
        $list = Db::name('order')->alias('o')->join('tp_order_goods g','g.order_id=o.order_id','left')->where($where)->order('o.order_id asc')->page($p.',10')->field('o.*,g.*,count(g.goods_num) as num')->group('goods_name')->select();
        $count = Db::name('order')->alias('o')->join('tp_order_goods g','g.order_id=o.order_id','left')->where($where)->group('goods_name')->count();
        $Page = new Page($count,10);
        $show = $Page->show();
        $this->assign('user_id',I('user_id'));
        $this->assign('user',I('user'));
        $this->assign('list',$list);
        $this->assign('page',$show);
        $this->assign('group_list',$group_list);
        $this->assign('inc_type',$inc_type);
        $this->assign('count',$count);
        return $this->fetch($inc_type);
    }
    /*
     * 奖项二四
     */
    public function award()
    {
        /*配置列表*/
        $group_list = [
            'award' => '奖项二',
            'award4'  => '奖项四',
        ];
        $inc_type =  I('get.inc_type','award');
        if($inc_type=='award'){
            $where['type']=14;
        }else{
            $where['type']=15;
        }
        $p = I('p')?:1;
        $list = Db::name('account_log')->where($where)->order('change_time desc')->page($p.',10')->select();
        $count = Db::name('account_log')->where($where)->count();
        $Page = new Page($count,15);
        $show = $Page->show();
        $this->assign('list',$list);
        $this->assign('page',$show);
        $this->assign('group_list',$group_list);
        $this->assign('inc_type',$inc_type);
        $this->assign('count',$count);
        return $this->fetch($inc_type);
    }
    /*
     * 平台收入统计
     */
    public function income_statistics()
    {
        /*配置列表*/
        $group_list = [
            'income_statistics' => '抢购专区利润',
            'income_statistics2'  => '提现手续费',
            'income_statistics3' => '寄售成功手续费',
        ];
        $inc_type =  I('inc_type','income_statistics');
        $status=I('status');

        $pagesize=15;
        $page=I('p')?:1;
        $create_time = I('create_time');
        if($create_time){
            $create_time = str_replace("+"," ",$create_time);
            $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
            $create_time3 = explode(' - ',$create_time2);
            $tmies= array(
                array('gt', strtotime($create_time3[0])),
                array('lt', strtotime($create_time3[1]))
            );
            $this->assign('create_time',$create_time2);
            $this->assign('start_time',I('start_time'));
            $this->assign('end_time',I('end_time'));
        }

        if($inc_type=='income_statistics'){
            $where['type']=1;
            $where['pay_status']=1;
            if($tmies){
                $where['pay_time'] =$tmies;
            }
            //按月
            if($status==2){
                $list = Db::name('order')->where($where)->field("FROM_UNIXTIME(pay_time,'%Y-%m') times,net_income,order_id")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }elseif($status==3){
                //按年
                $list = Db::name('order')->where($where)->field("FROM_UNIXTIME(pay_time,'%Y') times,net_income,order_id")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }else{
                //按日
                $list = Db::name('order')->where($where)->field("FROM_UNIXTIME(pay_time,'%Y-%m-%d') times,net_income,order_id")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }
        }elseif($inc_type=='income_statistics2'){
            $where['type']=5;
            if($tmies){
                $where['createTime'] =$tmies;
            }
            //按月
            if($status==2){
                $list = Db::name('shoppinglog')->where($where)->field("FROM_UNIXTIME(unix_timestamp(createTime),'%Y-%m') times,num net_income")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=abs($value2['net_income']);
                        }
                    }
                }
            }elseif($status==3){
                //按年
                $list = Db::name('shoppinglog')->where($where)->field("FROM_UNIXTIME(unix_timestamp(createTime),'%Y') times,num net_income")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=abs($value2['net_income']);
                        }
                    }
                }
            }else{
                //按日
                $list = Db::name('shoppinglog')->where($where)->field("FROM_UNIXTIME(unix_timestamp(createTime),'%Y-%m-%d') times,num net_income")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=abs($value2['net_income']);
                        }
                    }
                }
            }
        }else{
            $where['type']=4;
            if($tmies){
                $where['createTime'] =$tmies;
            }
            //按月
            if($status==2){
                $list = Db::name('shoppinglog')->where($where)->field("FROM_UNIXTIME(unix_timestamp(createTime),'%Y-%m') times,num net_income")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }elseif($status==3){
                //按年
                $list = Db::name('shoppinglog')->where($where)->field("FROM_UNIXTIME(unix_timestamp(createTime),'%Y') times,num net_income")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }else{
                //按日
                $list = Db::name('shoppinglog')->where($where)->field("FROM_UNIXTIME(unix_timestamp(createTime),'%Y-%m-%d') times,num net_income")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }
        }
        $count=count($list3);
        $start=($page-1)*$pagesize;//偏移量，当前页-1乘以每页显示条数
        if($count>0){
            $list3 = array_slice($list3,$start,$pagesize);
        }
        $Page = new Page($count,$pagesize);
        $show = $Page->show();

        $this->assign('list',$list3);
        $this->assign('page',$show);
        $this->assign('group_list',$group_list);
        $this->assign('inc_type',$inc_type);
        $this->assign('count',$count);
        return $this->fetch($inc_type);
    }
    /*
     * 总营业额
     */
    public function turnover()
    {
        /*配置列表*/
        $group_list = [
            'turnover' => '抢购专区订单',
            'turnover2'  => '批发专区订单',
        ];
        $inc_type =  I('inc_type','turnover');
        $status=I('status');

        $pagesize=15;
        $page=I('p')?:1;
        $create_time = I('create_time');
        if($create_time){
            $create_time = str_replace("+"," ",$create_time);
            $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
            $create_time3 = explode(' - ',$create_time2);
            $tmies= array(
                array('gt', strtotime($create_time3[0])),
                array('lt', strtotime($create_time3[1]))
            );
            $this->assign('create_time',$create_time2);
            $this->assign('start_time',I('start_time'));
            $this->assign('end_time',I('end_time'));
        }

        if($inc_type=='turnover'){
            $where['type']=1;
            $where['pay_status']=1;
            if($tmies){
                $where['pay_time'] =$tmies;
            }
            //按月
            if(empty($status)){
                $list = Db::name('order')->where($where)->field("FROM_UNIXTIME(pay_time,'%Y-%m') times,order_amount net_income,order_id")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }else{
                //按日
                $list = Db::name('order')->where($where)->field("FROM_UNIXTIME(pay_time,'%Y-%m-%d') times,order_amount net_income,order_id")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }
        }else{
            $where['type']=0;
            $where['pay_status']=1;
            if($tmies){
                $where['pay_time'] =$tmies;
            }
            //按月
            if(empty($status)){
                $list = Db::name('order')->where($where)->field("FROM_UNIXTIME(pay_time,'%Y-%m') times,order_amount net_income,order_id")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }else{
                //按日
                $list = Db::name('order')->where($where)->field("FROM_UNIXTIME(pay_time,'%Y-%m-%d') times,order_amount net_income,order_id")->select();
                $list1=[];
                foreach ($list as $key => $value) {
                    $list1[$key]=$value['times'];
                }
                $list2=array_unique($list1);
                foreach ($list2 as $key1 => $value1) {
                    $list3[$key1]['net_income']=0;
                    foreach ($list as $key2 => $value2) {
                        if($value1==$value2['times']){
                            $list3[$key1]['times']=$value2['times'];
                            $list3[$key1]['net_income']+=$value2['net_income'];
                        }
                    }
                }
            }
        }
        $count=count($list3);
        $start=($page-1)*$pagesize;//偏移量，当前页-1乘以每页显示条数
        if($count>0){
            $list3 = array_slice($list3,$start,$pagesize);
        }
        $Page = new Page($count,$pagesize);
        $show = $Page->show();

        $this->assign('list',$list3);
        $this->assign('status',$status);
        $this->assign('page',$show);
        $this->assign('group_list',$group_list);
        $this->assign('inc_type',$inc_type);
        $this->assign('count',$count);
        return $this->fetch($inc_type);
    }
    //会员列表
    public function index(){
        return $this->fetch();
    }
    //会员升级记录
    public function director(){
        $where = [];
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $where['change_time'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
        }
        if(isset($_GET['condition']) && isset($_GET['search_key'])){
            if($_GET['condition'] == '' ){
                unset($_GET['condition']);
            }
            switch($_GET['condition']){
                // 账号
                case '1' :
                    $where['g.account'] =  $_GET['search_key'];
                    break;
                // ID
                case '2' :
                    $where['g.user_id'] =  $_GET['search_key'];
                    break;

            }
        }
        $data = Db::name('vpay_level_log')->alias('g')
            ->join('user_level l','l.level_id = g.before_level','LEFT')
            ->field('g.*,l.level_name')
            ->where($where)
            ->order('id desc')
            ->select();
        $pagesize=15;
        $page=I('p')?:1;
        $count=count($data);
        $start=($page-1)*$pagesize;//偏移量，当前页-1乘以每页显示条数
        if($count>0){
            $list3 = array_slice($data,$start,$pagesize);
        }
        $Page = new Page($count,$pagesize);
        $show = $Page->show();
        $this->assign('page',$show);
        $this->assign('count',$count);
        $this->assign('data', $data);
        return $this->fetch();
    }
    /*
     * 基础配置
     */
    public function handle()
    {
        $inc_type='ylg_spstem_role';
        if(IS_POST){
            $param = I('post.');
            if($param['push'] && $param['return_numbers']){
                foreach($param['push'] as $key=>$val){
                    $result['pushs'][$key]['sales']  = $val;
                    $result['pushs'][$key]['rebate']  = $param['return_numbers'][$key];
                }
                $param['pushs'] = serialize($result['pushs']);
                unset($param['push']);
                unset($param['return_numbers']);
            }
            tpCache($inc_type,$param);
        }
        $config = tpCache($inc_type);
        if($config['pushs']){
            $config['pushs'] = unserialize($config['pushs']);
            $config['integral'] = unserialize($config['integral']);
        }

        $this->assign('config',$config);//当前配置项
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex(){
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['account'] = I('email') : false;
        I('id') ? $condition['user_id'] = I('id') : false;
        I('nickname') ? $condition['nickname'] = ['like','%'.I('nickname').'%'] : false;

        I('level') && ($condition['level'] = I('level'));
        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        $sort_order = I('order_by').' '.I('sort');
//        dump($condition);die;
        $model = M('users');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,14);

        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if ($key == 'nickname'){
                $Page->parameter[$key]   =  trim($val[1]);
                continue;
            }
            $Page->parameter[$key]   =   urlencode($val);

        }
        $system = tpCache('ylg_spstem_role');
        foreach ($userList as $key1=>$value){
            $userList[$key1]['award2']='否';
            if($value['level']==3){
                $res=Db::name('users')->where(['first_leader'=>['in',$value['user_id']],'level'=>['in','1,2,3,4'],'monthly_performance'=>['>=',$system['consumption']]])->select();
                if(count($res)>=$system['user_num']){
                    $userList[$key1]['award2']='是';
                }
            }elseif($value['level']==4){
                $userList[$key1]['award2']='是';
            }
            if(($value['performance']+$value['monthly_performance']) >= $system['agent_num2']){
                $userList[$key1]['award3']='无限层2';
            }elseif(($value['performance']+$value['monthly_performance']) >= $system['agent_num1']){
                $userList[$key1]['award3']='无限层1';
            }else{
                $userList[$key1]['award3']='否';
            }
        }

        $show = $Page->show();
        $this->assign('userList',$userList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    /**
     * 董事列表
     */
    public function ajaxdirector(){
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;
        I('id') ? $condition['user_id'] = I('id') : false;
        I('nickname') ? $condition['nickname'] = ['like','%'.I('nickname').'%'] : false;

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by').' '.I('sort');
        $condition['level']=4;
//        dump($condition);die;
        $model = M('users');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,10);

        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if ($key == 'nickname'){
                $Page->parameter[$key]   =  trim($val[1]);
                continue;
            }
            $Page->parameter[$key]   =   urlencode($val);

        }

        $user_id_arr = get_arr_column($userList, 'user_id');
        if(!empty($user_id_arr))
        {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(".  implode(',', $user_id_arr).")  group by first_leader");
            $first_leader = convert_arr_key($first_leader,'first_leader');

            $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(".  implode(',', $user_id_arr).")  group by second_leader");
            $second_leader = convert_arr_key($second_leader,'second_leader');

            $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(".  implode(',', $user_id_arr).")  group by third_leader");
            $third_leader = convert_arr_key($third_leader,'third_leader');
        }
        $this->assign('first_leader',$first_leader);
        $this->assign('second_leader',$second_leader);
        $this->assign('third_leader',$third_leader);
        $show = $Page->show();
        $this->assign('userList',$userList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    /*
     * 收益积分明细
     * 1：充值；2：申请提现；3：提现失败返还 4：直推下级业绩返点 5：代理商补贴  6 : 直推代理商收入 7：月销售额收入；8：收益积分购买产品；9：转出；10：转入； 11：寄售商品收入 12：合伙人补贴；13：转让手续费 14 : 回收出售商品；15消费专区收益积分购买；16：代理商团队奖；17：董事团队奖；18：直推奖；19：团队新增业绩奖励；20：平台分红,21:余额兑换
     */
    public function balance()    {

        //判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition){
            case 1: //手机
                $where['m.mobile'] = $search_key;
                break;
            case 2: //账号
                $where['m.account'] = $search_key;
                break;
            case 3: // 用户ID
                $where['t.userId'] = $search_key;
                break;
            case 4: // 订单ID
                $where['t.reflectId'] = $search_key;
                $where['t.type'] = ['in','8,11,14,15'];
                break;
            default:
                break;
        }

        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $where['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }

        // 分页输入
        if(empty($pageSize)){
            $pageSize = 15;
        }

        // 总条数
        $count = Db::name('balancelog')
            ->alias("t")->join("tp_users m ", " m.user_id=t.userId", 'LEFT')
            ->where($where)
            ->count();
        $page = new Page($count, $pageSize);
        $show = $page->show();


        // 进行分页数据查询
        $list = M('balancelog')
            ->alias("t")
            ->join("tp_users m ","m.user_id=t.userId", 'LEFT')
            ->field("t.*,m.nickname,m.mobile,m.account")
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t.id DESC')
            ->select();
        if (!empty($list)) {
            foreach ($list as $k=>$v) {
                //转入转出人

                /*if($v['type']==1){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m "," m.id=t.userId", 'LEFT')
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")->find();
                    $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                }
                if($v['type']==2){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m", "m.id = t.toUserId", 'LEFT')
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")
                        ->find();
                    $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                }*/

                switch ($v['type']) {
                    case 1 :
                        $list[$k]['type_str'] = "充值";
                        break;
                    case 2 :
                        $list[$k]['type_str'] = "申请提现";
                        break;
                    case 3 :
                        $list[$k]['type_str'] = "提现失败返还";
                        break;
                    case 4 :
                        $list[$k]['type_str'] = "直推下级业绩返点";
                        break;
                    case 5 :
                        $list[$k]['type_str'] = "代理商补贴";
                        break;
                    case 6 :
                        $list[$k]['type_str'] = "直推代理商收入";
                        break;
                    case 7 :
                        $list[$k]['type_str'] = "月销售额收入";
                        break;
                    case 8 :
                        $list[$k]['type_str'] = "批发专区购买产品";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 9 :
                        $list[$k]['type_str'] = "转出";
                        break;
                    case 10 :
                        $list[$k]['type_str'] = "转入";
                        break;
                    case 11 :
                        $list[$k]['type_str'] = "寄售商品收入";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 12 :
                        $list[$k]['type_str'] = "合伙人补贴";
                        break;
                    case 13 :
                        $list[$k]['type_str'] = "转让手续费";
                        break;
                    case 14 :
                        $list[$k]['type_str'] = "回收出售商品";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 15 :
                        $list[$k]['type_str'] = "抢购专区购买产品";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 16 :
                        $list[$k]['type_str'] = "代理商团队奖";
                        break;
                    case 17 :
                        $list[$k]['type_str'] = "董事团队奖";
                        break;
                    case 18 :
                        $list[$k]['type_str'] = "直推奖（奖项一）";
                        break;
                    case 19 :
                        $list[$k]['type_str'] = "团队新增业绩奖励（奖项三）";
                        break;
                    case 20 :
                        $list[$k]['type_str'] = "平台分红(奖项二)";
                        break;
                    case 21 :
                        $list[$k]['type_str'] = "收益兑换";
                        break;
                    case 22 :
                        $list[$k]['type_str'] = "平台分红(奖项四)";
                        break;
                    case 23 :
                        $list[$k]['type_str'] = "申请失败返还";
                        break;
                    case 24 :
                        $list[$k]['type_str'] = "申请高级会员";
                        break;
                    default:
                        $list[$k]['type_str'] = "收入";
                        break;
                }
            }
        }
        // 统计
        // $sum = M('balancelog')
        //     ->alias("t")->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
        //     ->field("count(1) as countNum")
        //     ->where($where)
        //     ->order('t.id DESC')
        //     ->find();

        // 输出数据
        $this->assign('list', $list);
        $this->assign('count', $count);

        $this->assign('page', $show);
        return $this->fetch();
    }
    /*
     * 配额明细
     * 1：收入；2：支出 8：购买商品 9：购物送配额 10 : 回收出售商品
     */
    public function integrallog()    {

        //判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition){
            case 1: //手机
                $where['m.mobile'] = $search_key;
                break;
            case 2: //账号
                $where['m.account'] = $search_key;
                break;
            case 3: // 用户ID
                $where['t.userId'] = $search_key;
                break;
            case 4: // 订单ID
                $where['t.reflectId'] = $search_key;
                $where['t.type'] = ['in','8,9,10'];
                break;
            default:
                break;
        }

        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $where['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }

        // 分页输入
        if(empty($pageSize)){
            $pageSize = 15;
        }

        // 总条数
        $count = Db::name('integrallog')
            ->alias("t")->join("tp_users m ", " m.user_id=t.userId", 'LEFT')
            ->where($where)
            ->count();
        $page = new Page($count, $pageSize);
        $show = $page->show();


        // 进行分页数据查询
        $list = M('integrallog')
            ->alias("t")
            ->join("tp_users m ","m.user_id=t.userId", 'LEFT')
            ->field("t.*,m.nickname,m.mobile,m.account")
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t.id DESC')
            ->select();
        if (!empty($list)) {
            foreach ($list as $k=>$v) {
                //转入转出人
                $list[$k]['operator'] = "";
                /*if($v['type']==1){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m "," m.id=t.userId", 'LEFT')
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")->find();
                    $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                }
                if($v['type']==2){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m", "m.id = t.toUserId", 'LEFT')
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")
                        ->find();
                    $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                }*/

                switch ($v['type']) {
                    case 1 :
                        $list[$k]['type_str'] = "收入";
                        break;
                    case 2 :
                        $list[$k]['type_str'] = "支出";
                        break;
                    case 8 :
                        $list[$k]['type_str'] = "购买商品";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 9 :
                        $list[$k]['type_str'] = "购物送积分";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 10 :
                        $list[$k]['type_str'] = "回收出售商品";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 11 :
                        $list[$k]['type_str'] = "转出";
                        break;
                    case 13 :
                        $list[$k]['type_str'] = "转出手续费";
                        break;
                    default:
                        $list[$k]['type_str'] = "收入";
                        break;
                }
            }
        }
        // 统计
        // $sum = M('balancelog')
        //     ->alias("t")->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
        //     ->field("count(1) as countNum")
        //     ->where($where)
        //     ->order('t.id DESC')
        //     ->find();

        // 输出数据
        $this->assign('list', $list);
        $this->assign('count', $count);

        $this->assign('page', $show);
        return $this->fetch();
    }
    /*
     * 购物积分明细
     * 1：收入；2：提现 ；4：平台管理费；5：提现手续费；8：购买商品 9：寄售返余额,10-兑换佣金
     */
    public function shoppinglog()    {

        //判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition){
            case 1: //手机
                $where['m.mobile'] = $search_key;
                break;
            case 2: //账号
                $where['m.account'] = $search_key;
                break;
            case 3: // 用户ID
                $where['t.userId'] = $search_key;
                break;
            case 4: // 订单ID
                $where['t.reflectId'] = $search_key;
                $where['t.type'] = ['in','9'];
                break;
            default:
                break;
        }

        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $where['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }

        // 分页输入
        if(empty($pageSize)){
            $pageSize = 15;
        }
        $where['t.userId']=['>',0];
        // 总条数
        $count = Db::name('shoppinglog')
            ->alias("t")->join("tp_users m ", " m.user_id=t.userId", 'LEFT')
            ->where($where)
            ->count();
        $page = new Page($count, $pageSize);
        $show = $page->show();


        // 进行分页数据查询
        $list = M('shoppinglog')
            ->alias("t")
            ->join("tp_users m ","m.user_id=t.userId", 'LEFT')
            ->field("t.*,m.nickname,m.mobile,m.account")
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t.id DESC')
            ->select();
        if (!empty($list)) {
            foreach ($list as $k=>$v) {
                //转入转出人
                $list[$k]['operator'] = "";
                // if($v['type']==1){
                //     $operator = M('transfer')
                //         ->alias("t")
                //         ->join("__MEMBER__ m "," m.id=t.userId", 'LEFT')
                //         ->where("t.id=".$v['reflectId'])
                //         ->field("m.nickname,m.id")->find();
                //     $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                // }
                // if($v['type']==2){
                //     $operator = M('transfer')
                //         ->alias("t")
                //         ->join("__MEMBER__ m", "m.id = t.toUserId", 'LEFT')
                //         ->where("t.id=".$v['reflectId'])
                //         ->field("m.nickname,m.id")
                //         ->find();
                //     $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                // }

                switch ($v['type']) {
                    case 1 :
                        $list[$k]['type_str'] = "收入";
                        break;
                    case 2 :
                        $list[$k]['type_str'] = "提现";
                        break;
                    case 3 :
                        $list[$k]['type_str'] = "提现失败返还";
                        break;
                    case 4 :
                        $list[$k]['type_str'] = "平台管理费";
                        break;
                    case 5 :
                        $list[$k]['type_str'] = "提现手续费";
                        break;
                    case 8 :
                        $list[$k]['type_str'] = "购买商品";
                        break;
                    case 9 :
                        $list[$k]['type_str'] = "寄售返收益";
                        $list[$k]['operator'] = $v['reflectId'];
                        break;
                    case 10 :
                        $list[$k]['type_str'] = "兑换余额";
                        break;
                    default:
                        $list[$k]['type_str'] = "收入";
                        break;
                }
            }
        }
        // 统计
        // $sum = M('balancelog')
        //     ->alias("t")->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
        //     ->field("count(1) as countNum")
        //     ->where($where)
        //     ->order('t.id DESC')
        //     ->find();

        // 输出数据
        $this->assign('list', $list);
        $this->assign('count', $count);

        $this->assign('page', $show);
        return $this->fetch();
    }
    /**
     * 更改会员等级  Lu
     */
    public function level_update(){
        $user_id = I('user_id');
        if(!$user_id > 0) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]);
        $user = M('users')->field('user_id,nickname,user_money,level,mobile,account')->where('user_id',$user_id)->find();
        if(IS_POST){
            $level = I('post.level');
            $desc = I('post.desc');
            if(!$level)
                $this->ajaxReturn(['status'=>0,'msg'=>"请选择会员等级"]);

            if($user['level']==$level){
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败，您没有对等级进行修改"]);
            }

            /*if(!$desc)
                $this->ajaxReturn(['status'=>0,'msg'=>"请填写操作说明"]);*/

            $data['level'] = $level;

            $res = M('users')->where('user_id',$user_id)->update($data);
            if($res)
            {
                vpay_level_log($user_id,$user['account'],'后台变更',$user['level'],$level,1);
                //adminLog("更改“". $user['nickname']."”等级 ：" . $user['level']."->".$level."，备注：".$desc);
                $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/User.User/index")]);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败"]);
            }
            exit;
        }

        $level_list =  M('user_level')->order('level_id asc')->select();
        $this->assign('level_list',$level_list);
        $this->assign('user_id',$user_id);
        $this->assign('user',$user);
        return $this->fetch();
    }

    /**
     * 会员详细信息查看
     */
    public function detail(){
        $uid = input('get.id');
        $user_model = new Users();
        $user = $user_model->where(['user_id'=>$uid])->find();
        $level_info = db('user_level')->where(['level_id'=>$user['level']])->find();

        if(!$user)
            exit($this->error('会员不存在'));
        if($this->request->method() == 'POST'){
            $data = input('post.');
            //  会员信息编辑
            if($data['password'] != '' && $data['password'] != $data['password2']){
                exit($this->error('两次输入登录密码不同'));
            }
            if($data['paypwd'] != '' && $data['paypwd'] != $data['paypwd2']){
                exit($this->error('两次输入安全密码不同'));
            }
            if($data['password'] == '' && $data['password2'] == ''){
                unset($data['password']);
            }else{
                if($user['oauth']){
                    $data['password'] = encrypt($data['password']);
                    $data['oauth'] = '';
                }else{
                    $data['password'] = encrypt($data['password']);
                }
            }
            if($data['paypwd'] == '' && $data['paypwd2'] == ''){
                unset($data['paypwd']);
            }else{
                if($user['oauth']){
                    $data['paypwd'] = encrypt($data['paypwd']);
                    $data['oauth'] = '';
                }else{
                    $data['paypwd'] = encrypt($data['paypwd']);
                }
            }

            if(!empty($data['email']))
            {   $email = trim($data['email']);
                $c = $user_model->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if(!empty($data['mobile']))
            {   $mobile = trim($data['mobile']);
                $c = $user_model->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            //更新分销关系
            if($user['first_leader'] != $data['first_leader']){
                $result = $this->change_distribution($uid,$data['first_leader']);
                if($result['status'] == 0){
                    exit($this->error($result['status']));
                }
            }

            $row = $user_model->where(['user_id'=>$uid])->save($data);
            if($row)
                exit($this->success('修改成功'));

            if($result['status'] == 1){
                exit($this->success('修改成功'));
            }
            exit($this->error('未作内容修改或修改失败'));
        }
        //下级信息
        $user['first_lower'] = $user_model->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = $user_model->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = $user_model->where("third_leader = {$user['user_id']}")->count();
        //上级信息
        $first_leader = $user_model->where(['user_id'=>$user['first_leader']])->find();

        $this->assign('user',$user);
        $this->assign('first_leader',$first_leader);
        $this->assign('level_info',$level_info);
        return $this->fetch();
    }

    /**
     * 更改会员的上级   Lu
     * @param int $user_id   被改用户
     * @param int $first_leader 上级用户
     * @return array
     */
    public function change_distribution($user_id=0,$first_leader=0){

        $user = D('users')->where(array('user_id'=>$user_id))->find();

        if($user_id==$first_leader){
            return array('status'=>0,'msg'=>'不能把自己设为上级');
        }

        $my_distribtion = M('users')->whereOr(array('first_leader'=>$user_id))->whereOr(array('second_leader'=>$user_id))->whereOr(array('third_leader'=>$user_id))->column('user_id');
        $first_leader_users =  D('users')->where(array('user_id'=>$first_leader))->find();

        $check_leader = checkLeader($user_id, $first_leader);
        if ($check_leader) {
            return array('status' => 0, 'msg' => '不能把自己的下级设为上级');
        }
        // if($my_distribtion){
        //     if(in_array($first_leader,$my_distribtion)){
        //         return array('status'=>0,'msg'=>'不能把自己的下级设为上级');
        //     }
        // }

        $new_leader['first_leader'] = $first_leader;
        $new_leader['second_leader'] = $first_leader_users['first_leader']?$first_leader_users['first_leader']:0;
        $new_leader['third_leader'] = $first_leader_users['second_leader']?$first_leader_users['second_leader']:0;

        //我的一级下级
        $my_first_distribution = M('users')->where(array('first_leader'=>$user_id))->column('user_id');
        //我的二级下级
        $my_second_distribution = M('users')->where(array('second_leader'=>$user_id))->column('user_id');
        //我的三级下级
        $my_third_distribution = M('users')->where(array('third_leader'=>$user_id))->column('user_id');

        //更改我的一级下级
        if($my_first_distribution){
            $data_first = array(
                'second_leader'=>$new_leader['first_leader'],
                'third_leader'=>$new_leader['second_leader'],
            );
            $res_first =M('users')->where(array('user_id'=>array('in',$my_first_distribution)))->save($data_first);
        }

        //更改我的二级下级
        if($my_second_distribution){
            $data_second = array(
                'third_leader'=>$new_leader['first_leader'],
            );
            $res_second =M('users')->where(array('user_id'=>array('in',$my_second_distribution)))->save($data_second);
        }

        $res1 = M('users')->where(array('user_id'=>$user_id))->update($new_leader);

        return array('status'=>1,'msg'=>'修改成功');
    }

    // 查找用户信息
    public function search_users()
    {
        $user_id = input('user_id');
        $tpl = input('tpl', 'search_users');
        $id = input('id', 0);
        $where = array();
        $where1 = array();

        $user = M('users')->where(array('user_id'=>$user_id))->find();
        $my_distribtion = M('users')->whereOr(array('first_leader'=>$user_id))->whereOr(array('second_leader'=>$user_id))->whereOr(array('third_leader'=>$user_id))->column('user_id');
        array_push($my_distribtion,$user['user_id']);

        $where['user_id'] = array('not in',$my_distribtion);

        if($id){
            $where1['user_id']=$id;
        }


        $model = M('users');
        $count = $model->where($where)->where($where1)->count();
        $Page  = new Page($count,5);
        //  搜索条件下 分页赋值
        $userList = $model->where($where)->where($where1)->order('user_id')->limit($Page->firstRow.','.$Page->listRows)->select();
//        $show = $Page->show();
        $this->assign('page', $Page);
        $this->assign('goodsList', $userList);
        return $this->fetch($tpl);
    }

    /**
     * 会员分销区域代理配置
     * Author:Faramita
     */
    public function detail_distribution(){
        $uid = input('get.id');
        if($this->request->method() == 'POST'){
            $row = db('users')->where(['user_id'=>$uid])->update(['region_code'=>input('post.update_region_code')]);
            if($row !== false)
                exit($this->success('修改成功'));
            exit($this->error('修改失败'));
        }
        //用户信息
        $user_model = new Users();
        $user = $user_model->where(['user_id'=>$uid])->find();
        //查找代理信息
        $level = db('user_level')->where(['is_region_agent'=>1,'level_id'=>$user['level']])->find()['region_code'];
        //存在一人代理多区域，用逗号分隔开存储
        $user_region = explode(',',$user['region_code']);
        //获取省市区数组
        $region = db('region')->select();
        $region_info = [];
        foreach($region as $k => $val){
            $region_info[$val['id']] = $val['parent_id'];
        }
        //省
        $region_content = '';
        $last_region_code = '';
        foreach($user_region as $k => $val){
            //判断代理级数
            if($level == 1){
                $region_code_province[$k] = $val ?: 1;
            }elseif($level == 2){
                $region_code_province[$k] = $region_info[$val] ?: 1;
                $region_code_city[$k] = $val ?: 2;
            }elseif($level == 3){
                $region_code_province[$k] = $region_info[$region_info[$val]] ?: 1;
                $region_code_city[$k] = $region_info[$val] ?: 2;
                $region_code_district[$k] = $val ?: 3;
            }
            //省代理
            $region_content .= "<div><select name='province[]' onchange='get_city_proxy(this,".$k.")' id='province".$k."'>";
            foreach($region as $ks => $vals){
                if($vals['parent_id'] == 0 && $vals['level'] == 1) {
                    if ($vals['id'] == $region_code_province[$k]) {
                        $region_content .= "<option selected value='" . $vals['id'] . "'>" . $vals['name'] . "</option>";
                    } else {
                        $region_content .= "<option value='" . $vals['id'] . "'>" . $vals['name'] . "</option>";
                    }
                }
            }
            $region_content .= "</select>";
            if($level >= 2){
                //市代理
                $region_content .= "<select name='city[]' onchange='get_area_proxy(this,".$k.")' id='city".$k."'>";
                foreach($region as $ks => $vals){
                    if($vals['parent_id'] == $region_code_province[$k]){
                        if($vals['id'] == $region_code_city[$k]){
                            $region_content .= "<option selected value='".$vals['id']."'>".$vals['name']."</option>";
                        }else{
                            $region_content .= "<option value='".$vals['id']."'>".$vals['name']."</option>";
                        }
                    }
                }
                $region_content .= "</select>";
            }
            if($level == 3){
                //区代理
                $region_content .= "<select name='district[]' id='district".$k."'>";
                foreach($region as $ks => $vals){
                    if($vals['parent_id'] == $region_code_city[$k]){
                        if($vals['id'] == $region_code_district[$k]){
                            $region_content .= "<option selected value='".$vals['id']."'>".$vals['name']."</option>";
                        }else{
                            $region_content .= "<option value='".$vals['id']."'>".$vals['name']."</option>";
                        }
                    }
                }
                $region_content .= "</select>";
            }
            $region_content .= '</div>';
        }
        //当前用户自身代理的区域不参与验证区域代理唯一性，所以需要将当前用户代理的区域配置为忽略区域
        if($user['region_code']){
            if($level == 1){
                $last_region_code = implode(',',$region_code_province);
            }elseif($level == 2){
                $last_region_code = implode(',',$region_code_city);
            }elseif($level == 3){
                $last_region_code = implode(',',$region_code_district);
            }
        }

        $this->assign('region_content', $region_content);
        $this->assign('last_region_code',$last_region_code);
        $this->assign('user',$user);
        $this->assign('level',$level);
        return $this->fetch();
    }

    public function add_user(){
        $level=Db::name('user_level')->order('level_id asc')->select();
        if(IS_POST){
            $data = I('post.');
            //设置初始角色
            //$data['level'] = 0;

            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if($res['status'] == 1){
                $this->success('添加成功',U('User.User/index'));exit;
            }else{
                $this->error('添加失败,'.$res['msg'],U('User.User/index'));
            }
        }
        $this->assign('level',$level);
        return $this->fetch();
    }

    public function export_user(){
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '</tr>';
        $count = M('users')->count();
        $p = ceil($count/5000);
        for($i=0;$i<$p;$i++){
            $start = $i*5000;
            $end = ($i+1)*5000;
            $userList = M('users')->order('user_id')->limit($start.','.$end)->select();
            if(is_array($userList)){
                foreach($userList as $k=>$val){
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['user_id'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['level'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['email'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['reg_time']).'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['last_login']).'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_money'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_points'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].' </td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .='</table>';
        downloadExcel($strTable,'users_'.$i);
        exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address(){
        $uid = I('get.id');
        $user_address_model = new \app\common\model\UserAddress();
        $lists = $user_address_model->where(array('user_id'=>$uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList',$regionList);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete(){
        $uid = I('get.id');
        $row = M('users')->where(array('user_id'=>$uid))->delete();
        if($row){
            $this->success('成功删除会员');
        }else{
            $this->error('操作失败');
        }
    }
    /**
     * 删除会员
     */
    public function ajax_delete(){
        $uid = I('id');
        if($uid){
            $row = M('users')->where(array('user_id'=>$uid))->delete();
            // 检查自动登录表是否含有该用户的信息
            $res = Db::name('oauth_users')->where(['user_id'=>$uid])->count();
            if ($res)
                $oauth_row = Db::name('oauth_users')->where(['user_id'=>$uid])->delete();
            if($row !== false){
                if ($res && $oauth_row) {
                    $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
                    exit;
                }
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }
    /**
     * 启用禁用会员
     */
    public function ajax_edit(){
        $uid = I('id');
        if($uid){
            $row = M('users')->where(array('user_id'=>$uid))->update(['engineer_status'=>I('type')]);
            if($row){
                $this->ajaxReturn(array('status' => 1, 'msg' => '操作成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '操作失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }
    /**
     * 账户资金记录
     */
    public function account_log(){
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('fund_adjustment_log')->where(array('user_id'=>$user_id))->count();
        $page = new Page($count);
        $lists  = M('fund_adjustment_log')->where(array('user_id'=>$user_id))->order('change_time desc')->limit($page->firstRow.','.$page->listRows)->select();

        $this->assign('user_id',$user_id);
        $this->assign('page',$page->show());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit(){
        $user_id = I('user_id');
        if(!$user_id > 0) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,distribut_money,mobile')->where('user_id',$user_id)->find();
        if(IS_POST){
            $data = I('post.');

            if(!empty($data['user_money'])){
                if($data['type1']==1){
                    $res=Db::name('users')->where("user_id in ($user_id)")->inc('user_money',$data['user_money'])->update();
                    $data2['user_money']=$data['user_money'];
                }else{
                    if($data['user_money']>$user['user_money']){
                        $this->ajaxReturn(['status'=>-1,'msg'=>"减少的数量不能大于当前数量"]);
                    }
                    $res=Db::name('users')->where("user_id in ($user_id)")->dec('user_money',$data['user_money'])->update();
                    $data2['user_money']=-$data['user_money'];
                }
            }
            if(!empty($data['frozen_money'])){
                if($data['type2']==1){
                    $res=Db::name('users')->where("user_id in ($user_id)")->inc('frozen_money',$data['frozen_money'])->update();
                    $data2['frozen_money']=$data['frozen_money'];
                }else{
                    if($data['frozen_money']>$user['frozen_money']){
                        $this->ajaxReturn(['status'=>-1,'msg'=>"减少的数量不能大于当前数量"]);
                    }
                    $res=Db::name('users')->where("user_id in ($user_id)")->dec('frozen_money',$data['frozen_money'])->update();
                    $data2['frozen_money']=-$data['frozen_money'];
                }
            }
            if(!empty($data['distribut_money'])){
                if($data['type3']==1){
                    $res=Db::name('users')->where("user_id in ($user_id)")->inc('distribut_money',$data['distribut_money'])->inc('distribut_money',$data['distribut_money'])->update();
                    $data2['distribut_money']=$data['distribut_money'];
                }else{
                    if($data['distribut_money']>$user['distribut_money']){
                        $this->ajaxReturn(['status'=>-1,'msg'=>"减少的数量不能大于当前数量"]);
                    }
                    $res=Db::name('users')->where("user_id in ($user_id)")->dec('distribut_money',$data['distribut_money'])->dec('distribut_money',$data['distribut_money'])->update();
                    $data2['distribut_money']=-$data['distribut_money'];
                }
            }

            if($res)
            {
                $data2['desc']='后台变更资金';
                $data2['user_id']=$data['user_id'];
                $data2['account']=$user['mobile'];
                $data2['change_time']=date('Y-m-d H:i:s');
                Db::name('fund_adjustment_log')->insert($data2);
                $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/User.User/account_log",array('id'=>$user_id))]);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败"]);
            }
            exit;
        }
        $this->assign('user_id',$user_id);
        $this->assign('user',$user);
        return $this->fetch();
    }
    //充值记录
    public function recharge(){
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $pay_status = I('pay_status');
        $map = array();
        if($timegap){
            $gap = explode(',', $timegap);
            $begin = $gap[0];
            $end = $gap[1];
            $map['ctime'] = array('between',array(strtotime($begin),strtotime($end)));
        }
        if($nickname){
            $map['nickname'] = array('like',"%$nickname%");
        }
        if($pay_status){
            if($pay_status==3){
                $map['pay_status'] = array('=',0);
            }else{
                $map['pay_status'] = array('=',$pay_status);
            }
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists  = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow.','.$page->listRows)->select();
        $this->assign('page',$page->show());
        $this->assign('pager',$page);
        $this->assign('lists',$lists);
        $this->assign('pay_status',$pay_status);
        return $this->fetch();
    }
    //充值审核
    public function recharge_review(){
        $data = I('get.');
        $lists = M('recharge')
            ->alias('r')
            ->join('tp_users u','u.user_id=r.user_id','right')
            ->where('r.order_id='.$data['order_id'])
            ->field('u.user_id,u.user_money,r.account')
            ->find();
        if($lists){
            $data['pay_time']=time();
            $res = M('recharge')->update($data);
            if ($res) {
                if($data['pay_status']==1){
                    M('users')->where('user_id='.$lists['user_id'])->setInc('user_money',$lists['account']);
                    balancelog($data['order_id'],$lists['user_id'],$lists['account'],1,$lists['user_money'],$lists['user_money']+$lists['account']);
                }
                $return = ['status' => 1, 'msg' => '审核成功'];
            } else {
                $return = ['status' => 0, 'msg' => '审核失败'];
            }
        }else{
            $return = ['status' => 0, 'msg' => '审核失败'];
        }

        $this->ajaxReturn($return);
    }

    /**
     * 角色详情
     * @return mixed
     * Author:Faramita
     */
    public function level(){
        $act = I('get.act','add');
        $this->assign('act',$act);
        $level_id = I('get.level_id');
        if($level_id){
            //获取处理好的配置数组
            $QualificationLogic = new QualificationLogic();
            //需要获取的配置类型
            $inc_type = $QualificationLogic->PUBLIC_INC_TYPE;

            $inc_info = $QualificationLogic->get_qualification_handle($level_id,$inc_type,1);
            $inc_info2 = $QualificationLogic->get_qualification_handle($level_id,$inc_type,2);
            //获取购买条件计算时机配置
            $update_point = $QualificationLogic->get_update_point($level_id,1);
            $update_point2 = $QualificationLogic->get_update_point($level_id,2);
            //获取角色信息
            $level_info = db('user_level')->where('level_id='.$level_id)->find();

            $this->assign('inc_info',$inc_info);
            $this->assign('inc_info2',$inc_info2);
            $this->assign('info',$level_info);
        }
        //购买条件计算时机无配置则默认是下单时
        $update_point = $update_point ?: 1;
        $update_point2 = $update_point2 ?: 1;
        //获取分类商品信息
        $GoodsLogic = new \app\admin\logic\GoodsLogic();
        $identity_list = $GoodsLogic->type_identity_list();

        $check_role = db('user_level')->field('level_id,level_name')->select();

        $this->assign('update_point',$update_point);
        $this->assign('update_point2',$update_point2);
        $this->assign('check_role',$check_role);
        $this->assign('identity_list',$identity_list);
        return $this->fetch();
    }

    public function levelList(){
        $Ad =  db('user_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level_id')->page($p.',10')->select();
        if($res){
            foreach ($res as $val){
                $list[] = $val;
            }
        }
        $this->assign('list',$list);
        $count = $Ad->count();
        $Page = new Page($count,10);
        $show = $Page->show();
        $this->assign('page',$show);
        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = db('user_level')->insert($data);
                if ($r !== false) {
                    //存储条件配置
                    $QualificationLogic = new QualificationLogic();
                    $QualificationLogic->qualification_handle($r,$data);
                    $QualificationLogic->set_update_point($r,$data['update_point'],1);
                    $QualificationLogic->set_update_point($r,$data['update_point2'],2);
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = db('user_level')->where('level_id=' . $data['level_id'])->update($data);
                if ($r !== false) {
                    //存储条件配置
                    $QualificationLogic = new QualificationLogic();
                    $QualificationLogic->qualification_handle($data['level_id'],$data);
                    $QualificationLogic->set_update_point($data['level_id'],$data['update_point'],1);
                    $QualificationLogic->set_update_point($data['level_id'],$data['update_point2'],2);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            //检测是否有属于该角色的用户，且不是初始角色
            $check_role_del = db('users')->where(['level'=>$data['level_id']])->select();
            if(empty($check_role_del) && $data['level_id'] != '1'){
                //删除角色
                $r = db('user_level')->where('level_id=' . $data['level_id'])->delete();
                //删除当前角色所有条件配置
                $QualificationLogic = new QualificationLogic();
                $del_one = $QualificationLogic->del_qualification_handle($data['level_id'],[],1);
                $del_two = $QualificationLogic->del_qualification_handle($data['level_id'],[],2);
                //删除当前角色其他配置
                $del_other = $QualificationLogic->del_qualification_handle($data['level_id'],[],0);

                if ($r !== false && $del_one['status'] && $del_two['status'] && $del_other['status']) {
                    $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
                } else {
                    $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
                }
            }else{
                $return = ['status' => 0, 'msg' => '删除失败，当前还有属于该角色的用户', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if(strstr($search_key,'@'))
        {
            $list = M('users')->where(" email like '%$search_key%' ")->select();
            foreach($list as $key => $val)
            {
                echo "<option value='{$val['user_id']}'>{$val['email']}</option>";
            }
        }
        else
        {
            $list = M('users')->where(" mobile like '%$search_key%' ")->select();
            foreach($list as $key => $val)
            {
                echo "<option value='{$val['user_id']}'>{$val['mobile']}</option>";
            }
        }
        exit;
    }

    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where("first_leader = 1")->select();
        return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users',$users);
        return $this->fetch();
    }

    /**
     * 发送系统消息
     * @author dyr
     * @time  2016/09/01
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $text= I('post.text');//内容
        $type = I('post.type', 0);//个体or全体
        $admin_id = session('admin_id');
        $users = I('post.user/a');//个体id
        $message = array(
            'admin_id' => $admin_id,
            'message' => $text,
            'category' => 0,
            'send_time' => time()
        );

        if ($type == 1) {
            //全体用户系统消息
            $message['type'] = 1;
            M('Message')->add($message);
        } else {
            //个体消息
            $message['type'] = 0;
            if (!empty($users)) {
                $create_message_id = M('Message')->add($message);
                foreach ($users as $key) {
                    M('user_message')->add(array('user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email= I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if($email){
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        return $this->fetch();
    }

    public function get_withdrawals_list($status=''){
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = I('create_time');
        $create_time = str_replace("+"," ",$create_time);
        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);
        $this->assign('start_time',$create_time3[0]);
        $this->assign('end_time',$create_time3[1]);
        $where['w.create_time'] =  array(
            array('gt', strtotime($create_time3[0])),
            array('lt', strtotime($create_time3[1]))
        );
        $status = empty($status) ? I('status') : $status;

        if(empty($status) && $status !== '0'){
//            $where['w.status'] =  array('lt',1);
        } else {
            $where['w.status'] = $status;
        }

        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like','%'.$realname.'%');
        $bank_card && $where['w.bank_card'] = array('like','%'.$bank_card.'%');
        $export = I('export');
        if($export == 1){
            $strTable ='<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if(is_array($remittanceList)){
                foreach($remittanceList as $k=>$val){
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['nickname'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['money'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['bank_name'].'</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">'.$val['bank_card'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['realname'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['create_time']).'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['remark'].'</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .='</table>';
            unset($remittanceList);
            downloadExcel($strTable,'remittance');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page  = new Page($count,20);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname,u.account')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow.','.$Page->listRows)->select();

        //  添加奖项信息
        $prizeLogic = new  \app\common\logic\DistributPrizeLogic();
        foreach ($list as $key => $val) {

            $prizeLogic->setUserId($val['user_id']);
            $prizeInfo = ['integral_prize'];
            $prize_res = $prizeLogic->getIntegralPrizeInfo($val['money']);
            if (empty($prize_res)){ // 奖项未开启 组装
                $list[$key]['is_prize'] = '未开启';
                $list[$key]['integral'] = 0;
            } else {
                $list[$key]['is_prize'] = $prize_res['is_prize'];
                $list[$key]['integral'] = empty($prize_res['integral']) ? 0 : $prize_res['integral'];
            }
        }


        $this->assign('create_time',$create_time2);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        C('TOKEN_ON',false);
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("withdrawals");
        $model->where('id ='.$_GET['id'])->delete();
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public  function editWithdrawals(){
        $id = I('id');
        $model = M("withdrawals");
        $withdrawals = $model->find($id);
        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();
        if($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];

        // lzz 查询奖项情况
        $prizeLogic = new  \app\common\logic\DistributPrizeLogic();
        $prizeLogic->setUserId($withdrawals['user_id']);
        $prize_res = $prizeLogic->getIntegralPrizeInfo($withdrawals['money']);
        if (empty($prize_res)){ // 奖项未开启 组装
            $withdrawals['is_prize'] = '未开启';
            $withdrawals['integral'] = 0;
        } else {
            $withdrawals['is_prize'] = $prize_res['is_prize'];
            $withdrawals['integral'] = empty($prize_res['integral']) ? 0 : $prize_res['integral'];
        }

        $this->assign('user',$user);
        $this->assign('data',$withdrawals);
        return $this->fetch();
    }

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update(){
        $id = I('id/a');
        $data['status']=$status = I('status');
        $data['remark'] = I('remark');
        if($status == 1) $data['check_time'] = time();
        if($status != 1) $data['refuse_time'] = time();
        $r = M('withdrawals')->where('id in ('.implode(',', $id).')')->update($data);
        if($r){
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"),'JSON');
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"),'JSON');
        }
    }
    // 用户申请提现
    public function transfer(){
        $id = I('selected/a');
        if(empty($id))$this->error('请至少选择一条记录');
        $atype = I('atype');
        if(is_array($id)){
            $withdrawals = M('withdrawals')->where('id in ('.implode(',', $id).')')->select();
        }else{
            $withdrawals = M('withdrawals')->where(array('id'=>$id))->select();
        }
        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        $prizeLogic = new  \app\common\logic\DistributPrizeLogic();
        foreach($withdrawals as $val){
            $user = M('users')->where(array('user_id'=>$val['user_id']))->find();
            if($user['user_money'] < $val['money'])
            {
                $data = array('status'=>-2,'remark'=>'账户余额不足');
                M('withdrawals')->where(array('id'=>$val['id']))->save($data);
                $this->error('账户余额不足');
            }else{
                $rdata = array('type'=>1,'money'=>$val['money'],'log_type_id'=>$val['id'],'user_id'=>$val['user_id']);
                if($atype == 'online'){
                    header("Content-type: text/html; charset=utf-8");
                    exit("功能正在开发中。。。");
                }else{
                    $prizeLogic->setUserId($val['user_id']);
                    $prizeInfo = ['integral_prize'];
                    $prize_res = $prizeLogic->distribut(['integral_prize'], $val['money']);

                    accountLog($val['user_id'], (($val['taxfee']+$val['money']) * -1), 0,"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id'=>$val['id']))->save(array('status'=>2,'pay_time'=>time()));
                    expenseLog($rdata);//支出记录日志
                }
            }
        }
        if($alipay['batch_num']>0){
            //支付宝在线批量付款
            include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!",U('remittance'),3);
    }

    public function passWithdrawals(){
        $id = input('id');
        $withInfo = M('withdrawals')->where(['id' => $id, 'status' => 0])->find();
        if (!$withInfo) {
            $this->error('无该记录');
            exit;
        }

        /*$prizeLogic = new  \app\common\logic\DistributPrizeLogic();
        $prizeLogic->setUserId($withInfo['user_id']);
        $prizeInfo = ['integral_prize'];
        $prize_res = $prizeLogic->distribut(['integral_prize'], $withInfo['money']);*/

        $data['check_time'] = time();
        //$data['pay_time'] = time();
        $data['status'] = 1;

        $res = M('withdrawals')->where(['id' => $id, 'status' => 0])->save($data);

        if ($res) {
            $this->success('操作成功');
        } else{
            $this->error('失败');
        }
    }

    /**
     *  处理会员提现申请
     */
    public function failWith(){
        $id = I('id');
        $withInfo = M('withdrawals')->alias('w')
            ->join('users u','u.user_id=w.user_id')
            ->where(['w.id' => $id, 'w.status' => 0])
            ->field('w.id,w.user_id,w.money,u.distribut_money,w.status,w.taxfee')
            ->find();
        if (!$withInfo) $this->error("无记录!");
        $num=$withInfo['money']+$withInfo['taxfee'];
        // 启动事务
        Db::startTrans();
        try{
            $r = M('withdrawals')->where(['id'=>$id])->save(['status' => 2, 'check_time' => time()]);
            Db::name('users')->where('user_id',$withInfo['user_id'])->setInc('distribut_money',$num);
            shoppinglog($withInfo['id'],$withInfo['user_id'], $num, 3, $withInfo['distribut_money'],$withInfo['distribut_money']+$num);

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }

        if($r){
            //accountLog($withInfo['user_id'], $withInfo['money'], 0, $desc = '提现不通过返回',  0, $withInfo['id'], '', 13);
            $this->success("操作成功");
        }else{
            $this->error("操作失败");
        }
    }

    public function transferWechat()
    {
        $id = input('id');
        $withInfo = M('withdrawals')->where(['id' => $id, 'status' => 0])->find();
        if (!$withInfo) $this->error("无记录!");

        $openid = M('OauthUsers')->where(array('user_id'=>$withInfo['user_id']))->value('openid');
        if (!$openid) $this->error("该用户不是微信用户!");

        $money = ($withInfo['money'] - $withInfo['taxfee']) * 100;
        $wechatLogic = new \app\common\logic\WechatLogic();

        $res = $wechatLogic->sendMoney($money,$openid, $withInfo['create_time']);
        if ($res['return_code']=='SUCCESS' && $res['result_code'] == 'SUCCESS'){

            $r = M('withdrawals')->where(array('id'=>$id))->save(array('check_time' => time(),'status'=>2,'pay_time'=>time()));
            $rdata = array('type'=>1,'money'=>$withInfo['money'],'log_type_id'=>$id,'user_id'=>$withInfo['user_id']);
            accountLog($withInfo['user_id'], -$withInfo['money'], 0,"管理员处理用户提现申请");
            expenseLog($rdata);//支出记录日志

            $prizeLogic = new  \app\common\logic\DistributPrizeLogic();
            $prizeLogic->setUserId($withInfo['user_id']);
            $prize_res = $prizeLogic->distribut(['integral_prize'], $withInfo['money']);
            $this->success("操作成功!");
        } else {
            $this->error($res['err_code_des']);
        }
    }

    /**
     * 用户升级等级申请列表
     * Author:Faramita
     */
    public function applyList(){
        $user_id = input('user_id');
        $create_time = input('create_time');
        $create_time = str_replace("+"," ",$create_time);
        $create_time2 = $create_time ? $create_time  : date('Y-m-d',strtotime('-30 day')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);

        $where['apply_time'] = ['gt',strtotime($create_time3[0]),'lt', strtotime($create_time3[1])];
        $status = empty($status) ? input('status') : $status;
        if(empty($status) || $status === '0'){
            $where['status'] = ['lt',1];
        }
        if($status === '0' || $status > 0) {
            $where['status'] = $status;
        }
        if($user_id){
            $where['user_id'] = $user_id;
        }
        $count = Db::name('user_apply')->where($where)->count();
        $Page  = new Page($count,20);
        $list = Db::name('user_apply')->where($where)->order("apply_id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        //地址列表
        $region = db('region')->select();
        foreach($region as $k => $val){
            $region_arr[$val['id']] = $val['name'];
        }
        //角色列表
        $role = db('user_level')->select();
        foreach($role as $k => $val){
            $role_arr[$val['level_id']] = $val['level_name'];
        }
        foreach($list as $k => $val){
            //地址拼接
            $list[$k]['address'] = $region_arr[$val['province']].$region_arr[$val['city']].$region_arr[$val['district']].$val['address'];
            if($val['region_code']){
                $list[$k]['region_code'] = $region_arr[$val['region_code']];
            }else{
                $list[$k]['region_code'] = '';
            }
            //申请的角色名称
            $list[$k]['role_name'] = $role_arr[$val['level']];
            //申请人当前角色
            $list[$k]['user_role'] = db('users')->where(['user_id'=>$val['user_id']])->find()['level'];
            $list[$k]['user_role'] = $role_arr[$list[$k]['user_role']];
        }

        $show = $Page->show();
        $this->assign('create_time',$create_time2);
        $this->assign('start_time',$create_time3[0]);
        $this->assign('end_time',$create_time3[1]);
        $this->assign('show',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    /**
     * 申请详情
     * Author:Faramita
     */
    public function apply_detail(){
        $apply_id = input('get.apply_id');
        $data = db('user_apply')->where(['apply_id'=>$apply_id])->find();
        $data['region_name'] = db('region')->where(['id'=>$data['region_code']])->find()['name'];
        $data['level_name'] = db('user_level')->where(['level_id'=>$data['level']])->find()['level_name'];
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 处理用户升级等级申请
     * Author:Faramita
     */
    public function applyList_update(){
        $str_apply_id = input('post.apply_id');
        $apply_id = explode(',',$str_apply_id);
        $data['status'] = input('post.status');
        $data['remark'] = input('post.remark');
        $data['handle_time'] = time();
        //申请通过
        if($data['status'] == 1){
            //先初始化验证状态，3=验证中
            $valid = db('user_apply')->where('apply_id in ('.$str_apply_id.')')->update(['validate_status'=>3]);
            if($valid === false){
                $this->ajaxReturn(['status'=>-1,'msg'=>'网络错误，请重新再试']);exit();
            }
            //角色列表信息获取
            $role = db('user_level')->select();
            foreach($role as $k => $val){
                $role_arr[$val['level_id']] = $val['region_code'];
            }
            //准备验证升级角色,此处是申请的地方----------标记<QualificationLogic>
            $qualificationLogic = new \app\common\logic\QualificationLogic();
            foreach($apply_id as $k => $val){
                //连表获取用户信息
                $user_info = DB::name('user_apply')->alias('ua')
                    ->field('ua.*,u.level as u_level')
                    ->join('__USERS__ u', 'u.user_id = ua.user_id', 'INNER')
                    ->where(['ua.apply_id'=>$val])->find();
                //判断等级
                if(($user_info['u_level'] == $user_info['level']) && $role_arr[$user_info['level']]){
                    //用户与申请等级相同，且申请的身份已开启了区域代理，判断为一人代理多区域，直接走特殊验证同级升级
                    $proxy = $qualificationLogic->update_special_proxy($user_info['user_id'],$user_info['level'],$user_info['region_code']);
                    if($proxy){
                        //验证通过，更新数据
                        $data['validate_status'] = 1;
                        $result = db('user_apply')->where(['apply_id'=>$val])->update($data);
                    }else{
                        //验证失败，更新数据
                        $result = db('user_apply')->where(['apply_id'=>$val])->update(['validate_status'=>2,'status'=>2]);
                        $error[$k] = '用户与申请等级相同，且申请的身份已开启了区域代理，判断为一人代理多区域，但验证失败';
                    }
                }elseif($user_info['u_level'] >= $user_info['level']){
                    //用户当前等级大于申请的等级，则验证失败，更新数据
                    $result = db('user_apply')->where(['apply_id'=>$val])->update(['validate_status'=>2,'status'=>2]);
                    $error[$k] = '用户当前等级大于申请的等级，判定验证失败';
                }else{
                    //验证用户是否符合条件,尝试升级等级
                    $qualificationLogic->prepare_update_level($user_info['user_id'],['apply_level'=>$user_info['level'],'region_code'=>$user_info['region_code']]);
                    //尝试升级后，判断用户是否升级成功
                    $check_update = db('users')->where(['user_id'=>$user_info['user_id'],'level'=>$user_info['level']])->find();
                    if($check_update){
                        //申请通过，更新数据
                        $data['validate_status'] = 1;
                        $result = db('user_apply')->where(['apply_id'=>$val])->update($data);
                        //申请的是体验店，需要生成门店
                        if($user_info['level'] == 4){
                            $suppliers_data['role_id'] = 9;//门店
                            $suppliers_data['account'] = $user_info['phone']; //  账户
                            $suppliers_data['password'] = encrypt($user_info['phone']); //密码
                            $suppliers_data['suppliers_name'] = $user_info['suppliers_name']; //  门店名称
                            $suppliers_data['suppliers_desc'] = $user_info['suppliers_desc']; // 门店描述
                            $suppliers_data['is_directly'] = 0; // 非直营门店
                            $suppliers_data['is_check'] = 1; //  门店状态
                            $suppliers_data['suppliers_contacts'] = $user_info['user_name']; // 门店联系人
                            $suppliers_data['suppliers_img'] = $user_info['store_img'];  //  门店照片
                            $suppliers_data['suppliers_phone'] = $user_info['phone']; // 门店联系方式
                            $suppliers_data['province_id'] = $user_info['province']; // 省
                            $suppliers_data['city_id'] = $user_info['city']; //市
                            $suppliers_data['district_id'] = $user_info['district']; //区
                            // 查询门店地址经纬度
                            $Supplier = new \app\admin\controller\Supplier\Supplier();
                            $suppliers_address = $Supplier->bmap($user_info['store_address']);
                            $suppliers_data['lon'] = $suppliers_address[0]; // 经度
                            $suppliers_data['lat'] = $suppliers_address[1]; // 纬度
                            $suppliers_data['address'] = $user_info['store_address'];// 门店地址
                            $suppliers_data['add_time'] = time(); // 添加时间

                            $suppliers_result = db('suppliers')->insert($suppliers_data);
                            if($suppliers_result == false){
                                $error[$k] = '体验店申请成功，生成门店失败';
                            }
                        }
                    }else{
                        //验证失败，更新数据
                        $result = db('user_apply')->where(['apply_id'=>$val])->update(['validate_status'=>2,'status'=>2]);
                        $error[$k] = '不符合申请身份条件，验证失败';
                    }
                }
                unset($user_info);
            }
            //验证失败或者数据库操作失败
            if($error || $result === false){
                $this->ajaxReturn(['status'=>-1,'msg'=>'操作失败或部分申请不符合条件','data'=>$error]);
            }else{
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }
        }else{
            $result = db('user_apply')->where('apply_id in ('.$str_apply_id.')')->update($data);
            if($result !== false){
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>'操作失败']);
            }
        }
    }
    /**
     *  转账汇款记录
     */
    public function remittance(){
        $status = I('status',1);
        $this->assign('status',$status);
        $this->get_withdrawals_list($status);
        return $this->fetch();
    }

    /**
     * 海报列表
     */
    public function poster(){
        return $this->fetch();
    }

    /**
     * ajax海报列表
     */
    public function ajax_poster(){
        $count =  M('poster')->count();
        $Page = new AjaxPage($count, 12);
        $posterList = M('poster')->order('type desc,id asc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $show = $Page->show();
        $this->assign('list', $posterList);
        $this->assign('page',$show);
        return $this->fetch();
    }

    /*
     * 添加海报
     * */
    public function addPoster(){
        if (IS_POST){
            $base64_img = trim($_POST['img']);
            $up_dir = './public/poster/';//存放在当前目录的upload文件夹下
            if(!file_exists($up_dir)){
                mkdir($up_dir,0777);
            }
            if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
                $type = $result[2];
                if(in_array($type,array('pjpeg','jpeg','jpg','gif','bmp','png'))){
                    $new_file = $up_dir.date('YmdHis_').'.'.$type;
                    if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))){
                        $img_path = str_replace('../../..', '', $new_file);

                        //  存入数据库
                        $data = $_POST;
                        foreach($data as $k=>$v){
                            $data[$k] = str_replace('px',"",$v);
                        }
                        if ($data['nk_color']){
                            $data['nk_color'] = explode('rgb(',$data['nk_color']);
                            $data['nk_color'] = explode(')',$data['nk_color'][1])[0];
                        }
                        if ($data['nk_font']){
                            $data['nk_font'] = explode('px',$data['nk_font'])[0];
                        }
                        $data['img'] = str_replace('../../..', '', '/public/poster/'.date('YmdHis_').'.'.$type);
                        $data['add_time'] = time();
                        $type = M('poster')->where(['type'=> 1])->count();
                        if (!$type){
                            $data['type'] = 1;
                        }
                        M('poster')->insertGetId($data);
                        exit(json_encode(['code' => 1, 'data' =>$img_path]));
//                         echo '图片上传成功</br><img src="' .$img_path. '">';
                    }else{
                        exit(json_encode(['code' => -1, 'data' =>'', 'msg' => '图片上传失败']));
                        $this->error('图片上传失败');
                    }
                }else{
                    exit(json_encode(['code' => -1, 'data' =>'', 'msg' => '图片上传类型错误']));
                }

            }else{
                exit(json_encode(['code' => -1, 'data' =>'', 'msg' => '文件错误']));
            }
            exit;
        }
        return $this->fetch();
    }

    public function delPoster(){
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        $id = rtrim($ids,",");

        // 删除此商品
        M("poster")->whereIn('id',$id)->delete();  //商品表

        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>url("Admin/User.User/poster")]);
    }

    /**
     * 删除海报
     */
    public function ajax_poster_delete(){
        $id = I('id');
        if($id){
            $row = M('poster')->where(array('id'=>$id))->delete();
            if($row !== false){
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    public function setDefault(){
        $id = I('id');
        if($id){
            $general = M('poster')->where(['type' => 1])->save(['type' => 0]);
            $default = M('poster')->where(['id' => $id])->save(['type' => 1]);
            if($default){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->error('参数错误');
        }
    }

    /**
     * 会员等级申请列表
     */
    public function level_apply(){
        $statusList = array(
            '0'=>'未审核',
            '1'=>'审核通过',
            '2'=>'审核不通过',
        );
        $this->assign('statusList',$statusList);
        return $this->fetch();
    }
    /**
     * 会员列表
     */
    public function ajax_level_apply(){
        // 搜索条件
        $condition = array();
        $status = I('status');
        I('mobile') ? $condition['u.mobile'] = I('mobile') : false;
        I('id') ? $condition['u.user_id'] = I('id') : false;
        I('account') ? $condition['u.account'] = ['like','%'.I('account').'%'] : false;

        if($status!=""&&$status!=null){
            $condition['status']=$status;
        }

        $sort_order = I('order_by').' '.I('sort');
        $model = M('user_level_apply');
        $count = $model->alias('a')
            ->join('users u','a.user_id=u.user_id','left')
            ->where($condition)->count();
        $Page  = new AjaxPage($count,15);

        $dataList = $model->alias('a')
            ->join('users u','a.user_id=u.user_id','left')
            ->field('a.*,u.account,u.mobile,u.nickname')
            ->where($condition)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if ($key == 'account'){
                $Page->parameter[$key]   =  trim($val[1]);
                continue;
            }
            $Page->parameter[$key]   =   urlencode($val);
        }

        $statusList = array(
            '0'=>'未审核',
            '1'=>'审核通过',
            '2'=>'审核不通过',
        );
        $show = $Page->show();
        $this->assign('statusList',$statusList);
        $this->assign('dataList',$dataList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    /**
     * 申请审核
     */
    public function level_apply_handle(){
        $id = I('id');
        $status =I('status');
        $apply = M('user_level_apply')->where(array('id'=>$id))->find();

        $user = M('users')->where(array('user_id'=>$apply['user_id']))->find();

        if(empty($apply)){
            $this->ajaxReturn(array('status'=>0,'msg'=>"未查询到记录信息"));
        }
        if($apply['status']!=0){
            $this->ajaxReturn(array('status'=>0,'msg'=>"该记录已经完成审核"));
        }

        if($user['level']>$apply['level_id']&&$status==1){
            $this->ajaxReturn(array('status'=>0,'msg'=>"操作失败，会员等级比申请等级高"));
        }

        if($status==1){
            $limit_num = tpCache('ylg_spstem_role.limit_num');
            //已有高级会员人数
            $level3_user_num = M('users')->where(array('level'=>$apply['level_id']))->count();
            if($level3_user_num>=$limit_num&&$limit_num>0){
                $this->ajaxReturn(array('status'=>0,'msg'=>"高级会员人数已达到限制人数：".$limit_num."人"));
            }
        }

        $res = M('user_level_apply')->where(array('id'=>$id))->update(array('status'=>$status,'uptate_time'=>time()));
        if($res&&$status==1){
            $res1 = M('users')->where(array('user_id'=>$apply['user_id']))->update(array('level'=>$apply['level_id']));
            vpay_level_log($user['user_id'],$user['account'],'后台升级',$user['level'],$user['level']+1,1);
        }elseif($res&&$status==2){
            M('users')->where(array('user_id'=>$apply['user_id']))->setInc('user_money',$apply['pay_money']);
            balancelog($apply['id'], $apply['user_id'], $apply['pay_money'], 23, $user['user_money'],$user['user_money']+$apply['pay_money']);
        }
        $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"));
    }

    /**
     * 余额兑换佣金列表
     */
    public function asset_exchange(){
        $statusList = array(
            '0'=>'未审核',
            '1'=>'兑换成功',
            '2'=>'兑换失败',
        );
        $this->assign('statusList',$statusList);
        return $this->fetch();
    }
    /**
     * 会员列表
     */
    public function ajax_asset_exchange(){
        // 搜索条件
        $condition = array();
        $status = I('status');
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('id') ? $condition['user_id'] = I('id') : false;
        I('account') ? $condition['account'] = ['like','%'.I('account').'%'] : false;

        if($status!=""&&$status!=null){
            $condition['status']=$status;
        }

        $sort_order = I('order_by').' '.I('sort');
        $model = M('asset_exchange');
        $count = $model->alias('a')
            ->join('users u','a.user_id=u.user_id','left')
            ->where($condition)->count();
        $Page  = new AjaxPage($count,15);

        $dataList = $model->alias('a')
            ->join('users u','a.user_id=u.user_id','left')
            ->field('a.*,u.account,u.mobile,u.nickname')
            ->where($condition)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if ($key == 'account'){
                $Page->parameter[$key]   =  trim($val[1]);
                continue;
            }
            $Page->parameter[$key]   =   urlencode($val);
        }

        $statusList = array(
            '0'=>'未审核',
            '1'=>'兑换成功',
            '2'=>'兑换失败',
        );
        $show = $Page->show();
        $this->assign('statusList',$statusList);
        $this->assign('dataList',$dataList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }
    //寄售列表
    public function consignment()
    {
//判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition) {
            case 1: //账号
                $where['s.account'] = $search_key;
                break;
            case 2: //商品ID
                $where['g.goods_id'] = $search_key;
                break;
            case 3: // 用户ID
                $where['g.user_id'] = $search_key;
                break;
            default:
                break;
        }
//        dump($where);
//        dump($search_key);
        $ctime = urldecode(I('ctime'));
        if ($ctime) {
            $gap = explode(' - ', $ctime);
            $this->assign('start_time', $gap[0]);
            $this->assign('end_time', $gap[1]);
            $this->assign('ctime', $gap[0] . ' - ' . $gap[1]);
            $where['t.createTime'] = array(array('gt', $gap[0]), array('lt', $gap[1]));
        }

        // 分页输入
        if (empty($pageSize)) {
            $pageSize = 10;
        }

        // 总条数
        $count = Db::name('goods_consignment')->alias('g')->join('users s','s.user_id = g.user_id')->where($where)->group('g.user_id,setmeal_id')->count();
        $page = new Page($count, $pageSize);
        $show = $page->show();


        // 进行分页数据查询
        $list = Db::name('goods_consignment')
            ->alias('g')
            ->join('users s','s.user_id = g.user_id')
            ->where($where)
            ->field('g.*,sum(num) nums,sum(surplus_num) surpl,s.account')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->group('g.user_id,setmeal_id')
            ->order('update_time')
            ->select();
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $list[$k]['create_time'] = date('Y-m-d',strtotime($v['create_time']));
                $setmeal = Db::name('goods_setmeal')->where('goods_id', $v['goods_id'])->select();
                for ($i = 0; $i < 5; $i++) {
                    if ($setmeal[$i]['id'] == $v['setmeal_id']) {
                        $list[$k]['name'] = '套餐' . ($i + 1);
                        break;
                    }
                }
            }
        }
//        dump($list);
        // 统计
//        $sum = M('balancelog')
//            ->alias("t")->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
//            ->field("count(1) as countNum")
//            ->where($where)
//            ->order('t.id DESC')
//            ->find();

        // 输出数据
        $this->assign('list', $list);
//        $this->assign('sum', $sum);

        $this->assign('page', $show);
        return $this->fetch();
    }
    /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList() {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList() {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule() {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }
}