<?php

namespace app\mobile\controller;

use app\common\logic\UsersLogic;
use app\common\logic\MessageLogic;
use app\common\logic\DistributLogic;
use think\Page;
use think\db;

class Engineer extends MobileBase
{
	public $user_id = 0;
	public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            //初始化账户信息
            DistributLogic::rebateDivide($this->user_id);   //初始获取分佣情况

            $this->assign('user', $user); //存储用户信息
        }

    }

    /**
     * 工程师主页
     */
    public function index(){
        $user_id =$this->user_id;
        $logic = new UsersLogic();
        $user = $logic->get_info($user_id); //当前登录用户信息

        // 获取当前工程师订单、评价
        $service_order = $logic->get_engineer_info($user_id);
        $this->assign('service_order',$service_order);
        $this->assign('accumulation',$service_order['accumulation']);
        $this->assign('account_all',$service_order['account_all']);
        $this->assign('account_month',$service_order['account_month']);
        $this->assign('wait',$service_order['wait']);
        $this->assign('user',$user['result']);
//        dump($user);exit;
        return $this->fetch();
    }

    /*
     * ajax获取服务订单信息
     * */
    public function ajax_get_order(){
    	$where = ' o.engineer_id = ' . $this->user_id;
        $type = I('get.type');
        if($type){
            $where .= C(strtoupper(I('get.type')));
//            $result['where'] = C(strtoupper(I('get.type')));
        }
    	$count = M('repair_order')->alias('o')->where($where)->count();
		$Page = new Page($count, 3);
        $show = $Page->show();
        $order_str = "o.order_id DESC";
        $order_list = Db::name('repair_order')
                        ->alias('o')
                        ->field('o.*,u.mobile as user_mobile,p.fault_id,p.cat_id,sp.suppliers_img')
                        ->order($order_str)
                        ->join('__REPAIR_PLAN__ p','o.plan_id = p.id','LEFT')
                        ->join('__USERS__ u','o.user_id = u.user_id','LEFT')
                        ->join('__SUPPLIERS__ sp','o.suppliers_id = sp.suppliers_id','LEFT')
                        ->where($where)
                        ->limit($Page->firstRow . ',' . $Page->listRows)
                        ->select();
//        $result['msg'] =  Db::name('repair_order')->getLastSql();

        foreach ($order_list as $k => &$v) {
            $v['order_cat'] = Db::name('repair_cat')->where(['id' => $v['cat_id']])->value('name');
            $v['order_fault'] = Db::name('repair_fault')->where(['id' => $v['fault_id']])->value('name');
        }
        $result['code'] = 1;
        $result['num'] = count($order_list);
        $result['data'] = $order_list;
        exit(json_encode($result));
    }

    //消息中心
    public function message(){

        return $this->fetch();
    }

    /**
     * ajax工程师消息通知请求
     */
    public function ajax_message(){

        $message_model = new MessageLogic();
        $user_logic = new UsersLogic();
        $user_sys_message = $message_model->getUserMessageNotice(0,1);
        foreach($user_sys_message as $k=>$v){
            $user_sys_message[$k]['data'] = unserialize($v['data']);
        }

        $this->assign('messages', $user_sys_message);
        return $this->fetch();
    }

    /**
     * ajax工程师公告请求
     */
    public function ajax_announcement(){
        $message_model = new MessageLogic();
        $common_notice_message = $message_model->getAnnouncement();
        $this->assign('announcement',$common_notice_message);

        return $this->fetch();
    }

    /**
     * ajax工程师公告详情
     */
    public function announcement_details(){
        $id = I('id');

        $where = array('article_id'=>$id);
        $news = M('article')->where($where)->find();

        if(!$news) $this->error('文章不存在了~', null, '', 5);

        $news['content'] = htmlspecialchars_decode( $news['content']);
        $this->assign('news',$news);

        return $this->fetch();
    }

    /**
     * ajax用户消息通知请求
     */
    public function set_message_notice()
    {
        $type = I('type');
        $msg_id = I('msg_id');
        $user_logic = new UsersLogic();
        $res =$user_logic->setMessageForRead($type,$msg_id);
        $this->ajaxReturn($res);
    }

    //工程师钱包
    public function englineer_account(){
        $user = session('user');
        $logic = new UsersLogic();
        $starttime = strtotime("-31 day");


        $data = $logic->get_account_log($this->user_id, I('get.type'),'1',$starttime,5);
        $account_log = $data['result'];
        $this->assign('account_log',$account_log);
        $this->assign('user', $user);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        $account_log_where = ['user_id'=>$this->user_id];
        $account_log_where['type'] = 1;
//        $time = time();
//        $account_log_where['change_time'] = array('between',array($starttime,$time));

        $sum = M('account_log')->where($account_log_where)->sum('user_money');
        $this->assign('sum',$sum);
        return $this->fetch();
    }

    /**
     * 服务订单详情
     */
    public function order_detail(){
        $id = I('id');
        if (!$id) $this->error('页面加载失败');
        $order_info = Db::name('repair_order')->where(['order_id' => $id])->find();
        if (!$order_info) $this->error('页面加载失败');
        $field = 'p.*,c.name as type,f.name,f.price,f.solution';

        // 查询故障信息
        $where['p.id'] = $order_info['plan_id'];
        $plan_info = Db::name('repair_plan')
            ->alias('p')
            ->field($field)
            ->join('tp_repair_cat c', 'p.cat_id = c.id')
            ->join('tp_repair_fault f', 'p.fault_id = f.id')
            ->where($where)
            ->find();

        //  查询订单商品
        if ($order_info['order_buy_id']){
            $order_goods = Db::name('order_goods')
                ->alias('og')
                ->field('g.id,g.goods_name,g.shop_price,g.original_img,g.goods_remark')
                ->join('__GOODS__ g','og.goods_id = g.goods_id')
                ->where(['order_id' => $order_info['order_buy_id']])
                ->select();
            $this->assign('goods', $order_goods);
        }

        // 查询订单日志
        $action_log = M('repair_action')->where(array('order_id' => $id))->order('log_time desc')->select();
//        dump($action_log);exit;

        $this->assign('action_log', $action_log);
        $this->assign('plan_info', $plan_info);
        $this->assign('order_info', $order_info);
        return $this->fetch();
    }

    /*
     * 工程师 修改
     * */
    public function repair(){
        $id = I('id/d');
        $order_info = Db::name('repair_order')->where(['order_id' => $id, 'engineer_id'=>$this->user_id])->find();
        if  (!$order_info) $this->error('无该订单信息');
        if  ($order_info['engineer_judge']) $this->error('该订单已评价');

        $this->assign('order', $order_info);
        if (!$id) $this->error('页面加载失败');
        return $this->fetch();
    }

    public function ajax_repair(){
        if (IS_POST){
            $id = I('id/d');
            $order_info = Db::name('repair_order')->where(['order_id' => $id, 'engineer_id'=>$this->user_id])->find();
            if  (!$order_info)  exit(json_encode(['code'=> -1,'msg'=>'无订单信息']));
            if  ($order_info['engineer_judge'])  exit(json_encode(['code'=> -1,'msg'=>'该订单已鉴定']));

            $paid_price = I('post.paid_price');
            $paid_price =  str_replace('￥','', $paid_price);
            $data['paid_price'] = $paid_price;
            $data['last_time'] = time();
            // 计算价格
            switch ($order_info['order_type']){
                case 0: // 寄修
                    $data['total_price'] = $paid_price;
                    $note = '寄修订单价格调整：'.$paid_price .', 订单总价为：'.$data['total_price'].' 支付价格为：'.$paid_price;
                    break;
                case 1: // 上门
                    $data['total_price'] = $paid_price + $order_info['prepaid_price'];
                    $data['pay_status'] = 0;
                    $note = '上门订单更新支付状态为未支付,价格调整：'.$paid_price .', 订单总价为：'.$data['total_price'].' 支付价格为：'.$paid_price;
                    break;
                case 2: // 到店
                    $data['total_price'] = $paid_price;
                    $note = '到店订单价格调整：'.$paid_price .', 订单总价为：'.$data['total_price'].' 支付价格为：'.$paid_price;
                    break;
            }
            $data['discount'] = $paid_price;
            //  过滤含有非法字符
            $data['engineer_judge'] = I('post.engineer_judge');
            $res = Db::name('repair_order')->where(['order_id' => $id])->save($data);
            $url = '/index.php/Mobile/Engineer/order_detail/id/'.$id;
            logServerOrder($id,'工程师修改订单',$note);
            empty($res)?exit(json_encode(['code'=> -1,'msg'=>'提交失败'])):exit(json_encode(['code'=> 1,'msg'=>'提交成功']));
        }
    }

    /*
     * 工程师完成订单
     * */
    public function confirm_order(){
        $id = I('id/d');
        $order_info = Db::name('repair_order')->where(['order_id' => $id, 'order_status' => 1, 'engineer_id'=>$this->user_id])->find();
        if  (!$order_info)
            exit(json_encode(['code'=> -1,'msg'=>'无订单信息或当前订单状态不在维修中']));
        if  (!$order_info['engineer_judge'])
            exit(json_encode(['code'=> -1,'msg'=>'当前订单未鉴定']));
        if ($order_info['pay_status'] == 0)
            exit(json_encode(['code'=> -1,'msg'=>'当前订单未支付']));

        $data['order_status'] = 2;
        $data['last_time'] = time();
        $res = Db::name('repair_order')->where(['order_id' => $id])->save($data);
        $note = '订单已完成';
        logServerOrder($id,'工程师修改订单',$note);
        $result = empty($res)?['code'=>-1,'msg'=>'修改失败，请检测当前订单']:['code'=>1,'msg'=>'修改成功'];
        exit(json_encode($result));
    }

    /*
     * 服务订单列表  工程师端
     * */
    public function order_list(){
        return $this->fetch();
    }

    /*
     * 获取数据
     * */
    public function ajax_order_list(){
        $type  = input('get.type');

        $order_where =['ro.engineer_id' => $this->user_id, 'ro.order_type'=>$type];
        $server_count = Db::name('repair_order')
            ->alias('ro')
            ->field('ro.*,og.goods_name,gd.original_img,gd.goods_remark,rp.id as plan_id,rp.fault_id,rp.cat_id,rf.name as fault_name,rf.price,pc.name as cat_name')
            ->join('__ORDER_GOODS__ og','ro.order_buy_id = og.order_id AND ro.order_state = 0','LEFT')
            ->join('__GOODS__ gd','og.goods_id = gd.goods_id AND ro.order_state = 0','LEFT')
            ->join('__REPAIR_PLAN__ rp','ro.plan_id = rp.id AND ro.order_state = 1','LEFT')
            ->join('__REPAIR_FAULT__ rf','rf.id = rp.fault_id AND ro.order_state = 1','LEFT')
            ->join('__REPAIR_CAT__ pc','pc.id = rp.cat_id AND ro.order_state = 1','LEFT')
            ->order('ro.add_time')
            ->where($order_where)
            ->count();
        $Page = new Page($server_count,5);

        $server_order = Db::name('repair_order')
            ->alias('ro')
            ->field('ro.*,og.goods_name,gd.original_img,gd.goods_remark,rp.id as plan_id,rp.fault_id,rp.cat_id,rf.name as fault_name,rf.price,pc.name as cat_name')
            ->join('__ORDER_GOODS__ og','ro.order_buy_id = og.order_id AND ro.order_state = 0','LEFT')
            ->join('__GOODS__ gd','og.goods_id = gd.goods_id AND ro.order_state = 0','LEFT')
            ->join('__REPAIR_PLAN__ rp','ro.plan_id = rp.id AND ro.order_state = 1','LEFT')
            ->join('__REPAIR_FAULT__ rf','rf.id = rp.fault_id AND ro.order_state = 1','LEFT')
            ->join('__REPAIR_CAT__ pc','pc.id = rp.cat_id AND ro.order_state = 1','LEFT')
            ->where($order_where)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->order('ro.add_time')
            ->select();
        $this->assign('server_order',$server_order);
//        dump(Db::name('repair_order')->getLastSql());exit;
        return $this->fetch();
    }

    /*
     * 查询当前订单是否工程师已处理
     * */
    public function order_deal_with(){
        $id = input('post.id');
        $info = Db::name('repair_order')->where(['order_id' => $id])->find();
        if ($info['engineer_judge'])
            $result = ['code'=> -1,'msg'=>'当前订单已鉴定'];
        else
            $result = ['code'=> 1,'msg'=>'ok'];
        exit(json_encode($result));
    }
}