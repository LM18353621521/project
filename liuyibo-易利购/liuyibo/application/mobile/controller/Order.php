<?php

namespace app\mobile\controller;

use app\common\model\TeamFound;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use think\Loader;
use think\Page;
use think\Request;
use think\db;
use think\Cookie;

class Order extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            $this->assign('user_id', $this->user_id);
        } else {
            header("location:" . U('User/login'));
            exit;
        }
        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $this->assign('order_status_coment', $order_status_coment);
    }

    /**
     * 订单列表
     * @return mixed
     */
    public function order_list()
    {
        $where = ' o.user_id=' . $this->user_id;
        $where .= ' AND type !=0';
        //条件搜索
        $type = I('get.type');
        if($type){
            $where .= C(strtoupper(I('get.type')));
        }
        $where.=' and order_prom_type < 5 ';//虚拟订单和拼团订单不列出来
        $count = M('order')->alias('o')->where($where)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $order_str = "o.order_id DESC";
        $order_list = Db::name('order')
                            ->alias('o')
                            ->field('o.*,ro.prepaid_price')
                            ->join('__REPAIR_ORDER__ ro','o.order_id = ro.order_buy_id','LEFT')
                            ->order($order_str)
                            ->where($where)
                            ->limit($Page->firstRow . ',' . $Page->listRows)
                            ->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            if ($type == 'WAITCCOMMENT') {
                $len = count($data['result']);
                if ($len > 1){
                    // 该订单的商品数目超过1  改变数据结构
                    foreach ($data['result'] as $key => $val){
                        $arr = $order_list[$k];
                        $arr['goods_list'][] = $val;
                        $order_list[] = $arr;
                    }
                    unset($order_list[$k]);
                } else {
                    $order_list[$k]['goods_list'] = $data['result'];
                }
            } else {
                $order_list[$k]['goods_list'] = $data['result'];
            }
        }
        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = 0;
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
                $num = Db::name('return_goods')->where("order_id = {$value['order_id']} AND goods_id = {$vv['goods_id']}")->count();
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
            $order_list[$key]['sqhh']=$num;
        }
       
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('page', $show);
        $this->assign('lists', $order_list);
        //dump($order_list);exit;
        $this->assign('active', 'order_list');
        $this->assign('active_status', I('get.type'));
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_order_list');
            exit;
        }
        return $this->fetch();
    }
    //拼团订单列表
    public function team_list(){
        $type = input('type');
        $Order = new \app\common\model\Order();
        $order_where = ['order_prom_type' => 6, 'user_id' => $this->user_id, 'deleted' => 0,'pay_code'=>['<>','cod']];//拼团基础查询
        switch (strval($type)) {
            case 'WAITPAY':
                //待支付订单
                $order_where['pay_status'] = 0;
                $order_where['order_status'] = 0;
                break;
            case 'WAITTEAM':
                //待成团订单
                $found_order_id = Db::name('team_found')->where(['user_id'=>$this->user_id,'status'=>1])->getField('order_id',true);//团长待成团
                $follow_order_id = Db::name('team_follow')->where(['found_user_id'=>$this->user_id,'status'=>1])->getField('order_id',true);//团员待成团
                $team_order_id = array_merge($found_order_id,$follow_order_id);
                if (count($team_order_id) > 0) {
                    $order_where['order_id'] = ['in', $team_order_id];
                }
                break;
            case 'WAITSEND':
                //待发货
                $order_where['pay_status'] = 1;
                $order_where['shipping_status'] = ['<>',1];
                $order_where['order_status'] = ['in','0,1'];
                break;
            case 'WAITRECEIVE':
                //待收货
                $order_where['shipping_status'] = 1;
                $order_where['order_status'] = 1;
                break;
            case 'WAITCCOMMENT':
                //已完成
                $order_where['order_status'] = 2;
                break;
        }
        $request = Request::instance();
        $order_count = $Order->where($order_where)->count();
        $page = new Page($order_count, 10);
        $order_list = $Order->with('orderGoods')->where($order_where)->limit($page->firstRow . ',' . $page->listRows)->order('order_id desc')->select();
        $this->assign('order_list',$order_list);
        if ($request->isAjax()) {
            return $this->fetch('ajax_team_list');
//            $this->ajaxReturn(['status'=>1,'msg'=>'获取成功','result'=>$order_list]);
        }
        return $this->fetch();
    }

    public function team_detail(){
        $order_id = input('order_id');
        $Order = new \app\common\model\Order();
        $TeamFound = new TeamFound();
        $order_where = ['order_prom_type' => 6, 'order_id' => $order_id, 'deleted' => 0];
        $order = $Order->with('orderGoods')->where($order_where)->find();
        if (empty($order)) {
            $this->error('该订单记录不存在或已被删除');
        }
        $orderTeamFound = $order->teamFound;
        if ($orderTeamFound) {
            //团长的单
            $this->assign('orderTeamFound', $orderTeamFound);//团长
        } else {
            //去找团长
            $teamFound = $TeamFound::get(['found_id' => $order->teamFollow['found_id']]);
            $this->assign('orderTeamFound', $teamFound);//团长
        }
        $this->assign('order', $order);
        return $this->fetch();
    }

    /**
     * 订单详情
     * @return mixed
     */
    public function order_detail()
    {
        $id = I('get.id/d');
        $map['o.order_id'] = $id;
        $map['o.user_id'] = $this->user_id;
        $order_info = M('order')->alias('o')->field('o.*,d.invoice_no,d.shipping_code')->join('__DELIVERY_DOC__ d', 'o.order_id = d.order_id', 'LEFT')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        // 获取订单物流表信息
        
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];

        $region_list = get_region_list();
        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id' => $id))->select();
//        echo "<pre>";
//        print_r( $this->user);exit;
        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('region_list', $region_list);
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);
        $this->assign('user', $this->user);
        if (I('waitreceive')) {  //待收货详情
            return $this->fetch('wait_receive_detail');
        }
        return $this->fetch();
    }

    /**
    * 取消订单
    */
    public function cancel_order()
    {
        $id = I('post.id/d');
        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data = $logic->cancel_order($this->user_id, $id);
        $this->ajaxReturn($data);
    }

    /**
     * 确定收货成功
     */
    public function order_confirm()
    {
        $id = I('id/d', 0);
        $data = confirm_order($id, $this->user_id);
        if(request()->isAjax()){
            $this->ajaxReturn($data);
        }
        if ($data['status'] != 1) {
            $this->error($data['msg'],U('Mobile/Order/order_list'));
        } else {
            $model = new UsersLogic();
            $order_goods = $model->get_order_goods($id);
            $this->assign('order_goods', $order_goods);
            return $this->fetch();
            exit;
        }
    }
    //订单支付后取消订单
    public function refund_order()
    {
        $order_id = I('get.order_id/d');

        $order = M('order')
            ->field('order_id,pay_code,pay_name,user_money,integral_money,coupon_price,order_amount')
            ->where(['order_id' => $order_id, 'user_id' => $this->user_id])
            ->find();

        $this->assign('user',  $this->user);
        $this->assign('order', $order);
        return $this->fetch();
    }
    //申请取消订单
    public function record_refund_order()
    {
        $order_id   = input('post.order_id', 0);
        $user_note  = input('post.user_note', '');
        $consignee  = input('post.consignee', '');
        $mobile     = input('post.mobile', '');

        $logic = new \app\common\logic\OrderLogic;
        $return = $logic->recordRefundOrder($this->user_id, $order_id, $user_note, $consignee, $mobile);

        $this->ajaxReturn($return);
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {
        $rec_id = I('rec_id',0);
        $return_goods = M('return_goods')->where(array('rec_id'=>$rec_id))->find();
        if(!empty($return_goods))
        {
            $this->redirect(U('Order/return_goods_info',array('id'=>$return_goods['id'])));
        }
        $order_goods = M('order_goods')->where(array('rec_id'=>$rec_id))->find();
        $order = M('order')->where(array('order_id'=>$order_goods['order_id'],'user_id'=>$this->user_id))->find();
        $confirm_time_config = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
        $confirm_time = $confirm_time_config * 24 * 60 * 60;
        if ((time() - $order['confirm_time']) > $confirm_time && !empty($order['confirm_time'])) {
            $this->error('已经超过' . $confirm_time_config . "天内退货时间");
//            return ['result'=>-1,'msg'=>'已经超过' . $confirm_time_config . "天内退货时间"];
        }
        if(empty($order))$this->error('非法操作');
        if(IS_POST)
        {
            $model = new OrderLogic();
            $res = $model->addReturnGoods($rec_id,$order);  //申请售后
            if($res['status']==1)$this->success($res['msg'],U('Order/return_goods_list'));
            $this->error($res['msg']);
        }
        $region_id[] = tpCache('shop_info.province');
        $region_id[] = tpCache('shop_info.city');
        $region_id[] = tpCache('shop_info.district');
        $region_id[] = 0;
        $return_address = M('region')->where("id in (".implode(',', $region_id).")")->getField('id,name');
        $this->assign('return_address', $return_address);
        $this->assign('goods', $order_goods);
        $this->assign('order',$order);
        return $this->fetch();
    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        //退换货商品信息
        $count = M('return_goods')->where("user_id", $this->user_id)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('return_goods')->where("user_id", $this->user_id)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');  //获取商品ID
        if (!empty($goods_id_arr)){
            $goodsList = M('goods')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        }
        $state = C('REFUND_STATUS');
        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('state',$state);
        $this->assign('page', $page->show());// 赋值分页输出
        if (I('is_ajax')) {
            return $this->fetch('ajax_return_goods_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id = I('id/d', 0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        $return_goods['seller_delivery'] = unserialize($return_goods['seller_delivery']);  //订单的物流信息，服务类型为换货会显示
        if ($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
        $state = C('REFUND_STATUS');
        $this->assign('state',$state);
        $this->assign('goods', $goods);
        $this->assign('return_goods', $return_goods);
        return $this->fetch();
    }

    public function return_goods_refund()
    {
        $order_sn = I('order_sn');
        $where = array('user_id'=>$this->user_id);
        if($order_sn){
            $where['order_sn'] = $order_sn;
        }
        $where['status'] = 5;
        $count = M('return_goods')->where($where)->count();
        $page = new Page($count,10);
        $list = M('return_goods')->where($where)->order("id desc")->limit($page->firstRow, $page->listRows)->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr))
            $goodsList = M('goods')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');
        $this->assign('goodsList', $goodsList);
        $state = C('REFUND_STATUS');
        $this->assign('list', $list);
        $this->assign('state',$state);
        $this->assign('page', $page->show());// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 取消售后服务
     * @author lxl
     * @time 2017-4-19
     */
    public function return_goods_cancel(){
        $id = I('id',0);
        if(empty($id))$this->error('参数错误');
        $return_goods = M('return_goods')->where(array('id'=>$id,'user_id'=>$this->user_id))->find();
        if(empty($return_goods)) $this->error('参数错误');
        M('return_goods')->where(array('id'=>$id))->save(array('status'=>-2,'canceltime'=>time()));
        $this->success('取消成功',U('Order/return_goods_list'));
        exit;
    }
    /**
     * 换货商品确认收货
     * @author lxl
     * @time  17-4-25
     * */
    public function receiveConfirm(){
        $return_id=I('return_id/d');
        $return_info=M('return_goods')->field('order_id,order_sn,goods_id,spec_key')->where('id',$return_id)->find(); //查找退换货商品信息
        $update = M('return_goods')->where('id',$return_id)->save(['status'=>3]);  //要更新状态为已完成
        if($update) {
            M('order_goods')->where(array(
                'order_id' => $return_info['order_id'],
                'goods_id' => $return_info['goods_id'],
                'spec_key' => $return_info['spec_key']))->save(['is_send' => 2]);  //订单商品改为已换货
            $this->success("操作成功", U("Order/return_goods_info", array('id' => $return_id)));
        }
        $this->error("操作失败");
    }

    /**
     *  评论晒单
     * @return mixed
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status = I('get.status');
        $logic = new \app\common\logic\CommentLogic;
        $result = $logic->getComment($user_id, $status); //获取评论列表
        $this->assign('comment_list', $result['result']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_comment_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *添加评论
     */
    public function add_comment()
    {
        if (IS_POST) {
            // 晒图片
            $files = request()->file('comment_img_file');
            $save_url = 'public/upload/comment/' . date('Y', time()) . '/' . date('m-d', time());
            foreach ($files as $file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $image_upload_limit_size = config('image_upload_limit_size');
                $info = $file->rule('uniqid')->validate(['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img[] = '/'.$save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }
            }
            if (!empty($comment_img)) {
                $add['img'] = serialize($comment_img);
            }

            $user_info = session('user');
            $logic = new UsersLogic();
            $add['goods_id'] = I('goods_id/d');
            $add['email'] = $user_info['email'];
            $hide_username = I('hide_username');
            if (empty($hide_username)) {
                $add['username'] = $user_info['nickname'];
            }
            $add['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
            $add['order_id'] = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
            $add['is_show'] = 1; //默认显示
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['add_time'] = time();
            $add['ip_address'] = request()->ip();
            $add['user_id'] = $this->user_id;
            //添加评论
            $row = $logic->add_comment($add);
            if ($row['status'] == 1) {
                $this->success('评论成功', U('/Mobile/Order/comment', array('status'=>1)));
                exit();
            } else {
                $this->error($row['msg']);
            }
        }
        $rec_id = I('rec_id/d');
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        $this->assign('order_goods', $order_goods);
        return $this->fetch();
    }

    /**
     * 待收货列表
     * @author lxl
     * @time   2017/1
     */
    public function wait_receive()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索
        if (I('type') == 'WAITRECEIVE') {
            $where .= C(strtoupper(I('type')));
        }
        $count = db('order')->alias('o')->where($where)->count();
        $pagesize = C('PAGESIZE');
        $Page = new Page($count, $pagesize);
        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = db('order')->alias('o')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }

        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = 0;
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
            //订单物流单号
            $invoice_no = M('DeliveryDoc')->where("order_id", $value['order_id'])->getField('invoice_no', true);
            $order_list[$key][invoice_no] = implode(' , ', $invoice_no);
        }
        $this->assign('page', $show);
        $this->assign('order_list', $order_list);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_wait_receive');
            exit;
        }
        return $this->fetch();
    }

    /*
     * 服务订单列表
     * */
    public function server_order_list(){
        return $this->fetch();
    }

    public function ajax_server_order_list(){
        $type  = input('get.type');
        $order_where =[ 'ro.user_id' => $this->user_id, 'ro.order_type'=>$type];
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
        return $this->fetch();
    }

    /**
     * 服务订单详情页面
     */
    public function server_order(){
        header('Cache-control: private, must-revalidate');
        $suppliers_id = input('get.suppliers_id/d');
        // 选择完门店会有门店id返回
        if (!empty($suppliers_id)){
            $field = 'suppliers_name,suppliers_desc,suppliers_phone,address';
            $suppliers_info = Db::name('suppliers')->field($field)->where(['suppliers_id'=>$suppliers_id])->find();
            //  radio默认选中
            !empty(input('get.type/d')) && $this->assign('type',input('get.type/d'));
            $this->assign('suppliers_id', $suppliers_id);
            $this->assign('suppliers_info', $suppliers_info);
//            dump($suppliers_info);exit;
        }
        // 接收维修订单的订单id  repair_plan 表
        $plan_id = input('get.id/d');
        $where['p.id'] = $plan_id;
        $field = 'p.*,c.name as type,f.name,f.price,f.solution';
        $plan_info = Db::name('repair_plan')
                            ->alias('p')
                            ->field($field)
                            ->join('tp_repair_cat c', 'p.cat_id = c.id')
                            ->join('tp_repair_fault f', 'p.fault_id = f.id')
                            ->where($where)
                            ->find();
        // 后面删  现在假设返回链接
        $this->assign('backUrl', $backUrl);
        $this->assign('plan_info', $plan_info);
        $this->assign('plan_id', $plan_id);
        return $this->fetch();
    }

    /*
     *  服务订单提交
     *  传订单id即为支付
     * */
    public function confirm_server_order(){
        $order_id = input('get.order_id/d');
        if (!empty($order_id)){
            // 查询订单
            $order_info = Db::name('repair_order')->where(['order_id' => $order_id])->find();
            if (!$order_info) exit(json_encode(['msg'=>'订单异常'])); // 无订单消息
            // 验证订单
            if ($order_info['pay_status']) exit(json_encode(['msg'=>'订单已支付']));
            
            $this->redirect('Mobile/Payment/getCodeServer', ['order_id' => $order_info['order_id'], 'pay_code' => 'weixin']);
            exit;
        }
        // 进行验证
        $validate = Loader::validate('Order');
        if(!$validate->check(input('post.'))){
            exit(json_encode(['code'=>-1,'msg'=>$validate->getError()]));
        }
        // 接收数据
        $suppliers_id = input('param.suppliers_id/d');
        $plan_id = input('post.plan_id/d');
        $order_type = input('post.order_type/d');
        $start_server_time = input('post.start_server_time/s',0);
        $end_server_time = input('post.end_server_time/s',0);
        $order_type = $order_type-1;
        $username = input('post.username/s');
        $phone = input('post.phone/s');
        $address = input('post.address/s');

        // 上门  到店需要选择服务时间
        if ($order_type != 0){
            if (empty($start_server_time))exit(json_encode(['code'=>-2,'msg'=>'请选择服务时间']));
            if (empty($end_server_time))exit(json_encode(['code'=>-3,'msg'=>'请选择服务时间']));
        }
        //  预付价格
        $prepaid_price = ($order_type == 1)? Db::name('config')->where(['name' => 'prepaid_price'])->value('value'):0;
        // 现在实付价格
        $paid_price = $prepaid_price;
        // 订单总价价格  预付 + 价格报价
        $total_price = $prepaid_price;
        //  寄修 到店订单 需要预估价格
        if ($order_type != 1){
            $paid_price = Db::name('repair_plan')
                ->alias('p')
                ->field('f.price')
                ->join('tp_repair_fault f', 'p.fault_id = f.id')
                ->where(['p.id' => $plan_id])
                ->find()['price'];
        }
        $orderLogic = new OrderLogic();
        $result = $orderLogic->addServerOrder(
            $this->user_id,
            $plan_id,
            $suppliers_id,
            $order_type,
            $prepaid_price,
            $paid_price,
            $total_price,
            $username,
            $address,
            $phone,
            $start_server_time,
            $end_server_time); // 添加订单
        if ($result['status'] == 1){
            //  下单成功  上门 支付鉴定费;
            if ($order_type == 1)
                exit(json_encode(['code'=>2,'msg'=>$result['msg'],'result'=>$result['result']]));
//            $this->redirect('Mobile/Payment/getCodeServer', ['order_id' => $result['result'], 'pay_code' => 'weixin']);
        else
            exit(json_encode(['code'=>1,'msg'=>$result['msg'],'result'=>$result['result']]));
        } else {
            exit(json_encode(['code'=>-4,'msg'=>'下单失败']));
        }
    }

    /*
     * ajax获取预支付金额
     * **/
    public function getConfig(){
        $info = Db::name('config')->where(['name' => 'prepaid_price'])->value('value');
        $msg = empty($info) ? '未配置': '';
        $code = empty($info) ? '-11': 1;
        exit(json_encode(['code' => $code,'msg' => $msg, 'result' => $info]));
    }

    /*
     * 订单支付页面
     * **/
    public function  pay_order(){
        $server_order_id = input('param.order_id/d');
        $info = Db::name('repair_order')->where(['order_id' => $server_order_id])->find();
        empty($info) && exit;
        $this->assign('info',$info);
        return $this->fetch();
    }

    /*
     * 服务订单详情页面
     * */
    public function  server_order_detail(){
        $order_id = input('order_id/d');
        empty($order_id) && $this->error('页面丢失',url('index/index'));
        $field = 'o.*,u.nickname,s.suppliers_contacts,s.suppliers_phone,s.province_id as suppliers_province,s.city_id  as suppliers_city,s.district_id  as suppliers_district,s.address as suppliers_address';
        // 查询订单
        $order_info = Db::name('repair_order')
                            ->alias('o')
                            ->field($field)
                            ->join('__USERS__ u', 'o.user_id = u.user_id', 'LEFT')
                            ->join('__SUPPLIERS__ s', 'o.suppliers_id = s.suppliers_id', 'LEFT')
                            ->where(['o.order_id' => $order_id, 'o.user_id'=>$this->user_id])
                            ->find();

        empty($order_info) && $this->error('无订单信息',url('index/index'));
        $order_info['suppliers_address'] = getTotalAddress($order_info['suppliers_province'],$order_info['suppliers_city'],$order_info['suppliers_district'],'',$order_info['suppliers_address']);
        $order_info['mobile'] = substr_replace($order_info['mobile'], '****',3, 4);
        $order_info['judge_status'] = empty($order_info['engineer_judge'])?1:2;

        // 如果已经安排工程师 查询工程师
        if (!empty($order_info['engineer_id'])){
            $logic = new UsersLogic();
            $engineer = $logic->get_engineer_info($order_info['engineer_id'], 1);
            $this->assign('engineer',$engineer);
        }

        // 如果存在商品id  查询商品信息
        if (!empty($order_info['order_buy_id'])){
            //获取订单商品
            $model = new UsersLogic();
            $goods = $model->get_order_goods($order_info['order_buy_id']);
            $this->assign('goods', $goods['result']);
        }

        // 如果当前状态为已评价  查看评价表是否含有评价信息
        if ($order_info['order_status'] == 3){
            $evaluation = Db::name('repair_evaluate')->where('order_id = '.$order_info['order_id'])->find();
            $is_evaluation = empty($evaluation) ? 1: 2;
            $this->assign('is_evaluation', $is_evaluation);
        }

        // 查询服务信息
        $where['p.id'] = $order_info['plan_id'];
        $field = 'p.*,c.name as type,f.name,f.price,f.solution';
        $plan_info = Db::name('repair_plan')
            ->alias('p')
            ->field($field)
            ->join('tp_repair_cat c', 'p.cat_id = c.id')
            ->join('tp_repair_fault f', 'p.fault_id = f.id')
            ->where($where)
            ->find();
        $backUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
        cookie('serverOrderDetailBackUrl',$backUrl,300);
        $this->assign('plan_info',$plan_info);
        $this->assign('order_info',$order_info);
        return $this->fetch();
    }

    /*
    *  服务订单ajax 提交
    * */
    public function  ajax_service_commit()
    {
        $order_id = input('post.order_id');
        $action = I('post.action');
        if (!$order_id) exit(json_encode(['code'=>-1, 'msg' => $order_id]));
        if (!$action) exit(json_encode(['code'=>-2, 'msg' => '请联系技术人员']));
        $order_info = Db::name('repair_order')->where(['order_id'=>$order_id])->find();
        if (!$order_info) exit(json_encode(['code'=>-3, 'msg' => '无订单消息.']));
        // 寄送 用户订单号
        if ($action == 'send') {
            // 验证 物流名称
            $shipping_code = trim(I('post.shipping_code'));
            $shipping_name = trim(I('post.shipping_name'));
            if(is_int($shipping_code))exit(json_encode(['code'=>-4, 'msg' => '物流订单有误']));
            $data['shipping_code'] = $shipping_code;
            $data['shipping_name'] = $shipping_name;
            $res = Db::name('repair_order')->where(["order_id"=> $order_id])->save($data);
            if ($res){
                //  修改成功  添加日志
                logServerOrder($order_id,'用户添加物流订单号','用户添加物流订单号:'.$shipping_code);
                exit(json_encode(['code'=>1, 'msg' => '提交成功']));
            }
            exit(json_encode(['code'=>-5, 'msg' =>'提交失败']));
        }
    }

    /*
     * 服务订单评价页面
     * */
    public function service_evaluation(){
        $id = input('get.order_id');
        if (!$id) $this->error('信息异常');
        $order_info = Db::name('repair_order')
                        ->field('order_id,engineer_id')
                        ->where(['order_id' => $id])
                        ->find();
        if (empty($order_info)) $this->error('无订单信息');
        $logic = new UsersLogic();
        $engineer = $logic->get_engineer_info($order_info['engineer_id'], 1);
        $this->assign('engineer',$engineer['engineer_info']);
        $this->assign('order_info',$order_info);
        return $this->fetch();
    }

    /*
    * 处理服务订单评价
    * */
    public function confirm_evaluation(){
        // 验证订单
        $order_id = input('post.order_id');
        if (!$order_id) exit(json_encode(['code'=>-1,'msg'=>'无订单信息']));
        $order = Db::name('repair_order')->where(['order_id' => $order_id, 'order_status'=>3])->find();
        if (!$order)exit(json_encode(['code'=>-1,'msg'=>'无订单信息']));
        $evaluate = Db::name('repair_evaluate')->where(['order_id' => $order_id])->find();
        if ($evaluate)exit(json_encode(['code'=>-1,'msg'=>'该订单已评价']));
        //  接收数据
        if (empty($_POST['evaluation'])) {
            $data['evaluation'] = '';
        } else {
            $data['evaluation'] = implode('-',$_POST['evaluation']);
        }

        $data['user_id'] = $this->user_id;
        $data['order_id'] = $order_id;
        $data['content'] = input('post.content');
        $data['user_rank'] = input('post.star');
        $data['rank_user'] = input('post.star');
        $data['add_time'] = time();
        $res = Db::name('repair_evaluate')->insertGetId($data);

        $order_result = Db::name('repair_order')->where(['order_id' => $order_id, 'order_status'=>3])->save(['order_status' => 4]);
        $code = -1;
        if ($res && $order_result) $code = 1;
        exit(json_encode(['code'=>$code,'msg'=>'']));
    }

    /**
     * 取消服务订单
     */
    public function cancel_server_order()
    {
        $id = I('get.id/d');
        //检查是否有积分，余额支付
        $logic = new OrderLogic();
        $data = $logic->cancel_server_order($this->user_id, $id);
        $this->ajaxReturn($data);
    }

    /*
    * 服务订单  确认完成  寄修 确认收货
    * */
    public function confirm_items(){

        $id = I('post.id/d');
        $action = I('post.action');
        if ($action){
            $where = ['order_id' => $id, 'order_status'=> 2];
            $note = '用户确认完成订单';
            $data['last_time'] = time();
        }else{
            $where =['order_id' => $id, 'order_type'=> 0, 'pay_status' => 1, 'order_status'=> 2,];
            $data['confirm_time'] = time();
            $data['last_time'] = time();
            $note = '用户确认收货';
        }
        $order_info = Db::name('repair_order')->where($where)->find();
        if (!$order_info) exit(json_encode(['code' => -1, 'msg' => '无订单信息或该订单已经评价或失效']));
        $data['order_status'] = 3;
        $res = Db::name('repair_order')->where(['order_id'=>$id])->save($data);
        if ($res) {

            // 工程师分钱
            // 判断当前订单门店是否是直营门店
            $engineer_fee = Db::name('config')->where(['name' => 'engineer_fee'])->value('value');
            $is_directly = Db::name('suppliers')->where(['suppliers_id' => $order_info['suppliers_id']])->value('is_directly');
            if  ($is_directly) { // 直营
                // 工程师钱 = 订单总价 - 工程师手续费
                $engineer =  round($order_info['total_price']  *  $engineer_fee /100, 2);

                $money = $order_info['total_price'] - $engineer;
                // $log = '工程师手续费比例：'.$engineer_fee;
                // $log .= '，工程师钱（'.$money.') = '.'订单总价（'.$order_info['total_price'].')  - 工程师手续费（'.$engineer.') ';

            } else { // 非直营

                $suppliers_fee = Db::name('config')->where(['name' => 'suppliers_fee'])->value('value');

                // 工程师钱 = 订单总价 - 非直营门店手续费 - 工程师手续费
                // 非直营门店手续费 =  订单总价 * 非直营门店手续费比例
                $suppliers = $order_info['total_price'] * $suppliers_fee / 100 ;
                // 门店收取工程师手续费 = (订单总价 - 非直营门店手续费)  *门店收取工程师手续费
                $engineer =  round(($order_info['total_price'] - $suppliers) *  $engineer_fee /100, 2);
                $money = $order_info['total_price'] - $suppliers - $engineer;
                // $log = '工程师手续费比例：'.$engineer_fee.' 非直营门店手续费比例:'.$suppliers_fee;
                // $log .= '，工程师钱（'.$money.') = '.'订单总价（'.$order_info['total_price'].') - 非直营门店手续费（'.$suppliers.') - 工程师手续费（'.$engineer.') ';
            }
            $log = '维修服务订单佣金';
            
            $money_res = Db::name('users')->where(['user_id' => $order_info['engineer_id']])->setInc('user_money',$money);
            if ($money_res) {
                // 添加资金流水
                $account_data['user_id'] = $order_info['engineer_id'];
                $account_data['user_money'] = $money;
                $account_data['change_time'] = time();
                $account_data['desc'] = $log;
                $account_data['order_sn'] = $order_info['order_sn'];
                $account_data['order_id'] = $order_info['order_id'];
                $account_data['type'] = 1;
                $account_res =Db::name('account_log')->insertGetId($account_data);
            }

            logServerOrder($id,$note,$note);
            exit(json_encode(['code'=>1, 'msg' => '确认成功']));
        }
        exit(json_encode(['code'=>-2, 'msg' =>'确认失败']));
    }

    /*
     * 快递一百物流查询 原Api/order 下面迁移
     * */
    public function express()
    {
        $order_id = I('get.order_id/d', 1553);
        if (!$order_id) {
            exit(json_encode(['code' => 500, 'msg'=>'系统出错']));
        }
        $delivery  = M('delivery_doc')->where("order_id", $order_id)->find();
        $order     = M('order')->where("order_id", $order_id)->find();
        $logistics = queryExpress($delivery['shipping_code'], $delivery['invoice_no']);
        if ($logistics['status'] == 200) {
            foreach ($logistics['data'] as $key => $value) {
                $time = strtotime($value['time']);
                $_t   =[
                    'specificdate' => date('m月d日', $time),
                    'timedivision' => date('H:i', $time),
                    'context'      => $value['context'],
                ];
                $list[] = $_t;
            }
            $status      = 200;
            $message     = '查询成功';
            $region_list = get_region_list();
            $data = [
                'shipping_name' => $delivery['shipping_name'],
                'invoice_no'    => $delivery['invoice_no'],
                'list'          => $list,
                'consignee'     => $order['consignee'],
                'address'       => $region_list[$order['province']] . $region_list[$order['city']] . $region_list[$order['district']] . $order['address'],
            ];
        } else {
            $message = $logistics['message'];
            $status  = 500;
            $data    = [
                'list' => '',
            ];
        }
        exit(json_encode(['code' => $status, 'msg' => $message, 'data' => $data]));
    }

    /*
     * 服务订单物流查询
     * */
    public function service_express(){
        $shipping_code = I('shipping_code');
        $shipping_name = I('shipping_name');
        if (!$shipping_name || !$shipping_code) $this->error('异常');
        $this->assign('shipping_code',$shipping_code);
        $this->assign('shipping_name',$shipping_name);
        return $this->fetch();
    }

    /**
     * 测试方法  后期可删
     */
    public function test(){
        $subject = ">>";
        dump(preg_match('/[><?》《*（）^~@$￥]/', $subject));

    }
}