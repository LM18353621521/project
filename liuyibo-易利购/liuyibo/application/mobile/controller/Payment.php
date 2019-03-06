<?php

namespace app\mobile\controller;
use think\Request;
use think\Db;
use app\common\logic\OrderLogic;
class Payment extends MobileBase {

    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code

    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        if(!empty($pay_radio))
        {
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        }
        else // 第三方 支付商返回
        {
            //$_GET = I('get.');
            //file_put_contents('./a.html',$_GET,FILE_APPEND);
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents('php://input');
//        if(empty($this->pay_code))
//            exit('pay_code 不能为空');
        // 导入具体的支付类文件
//        include_once  "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php"; // D:\wamp\www\svn_tpshop\www\plugins\payment\alipay\alipayPayment.class.php
//        $code = '\\'.$this->pay_code; // \alipay
//        $this->payment = new $code();
    }

    public function paypwd()
    {

    }

    /**
     *  提交支付方式
     */
    public function getCode(){
            //C('TOKEN_ON',false); // 关闭 TOKEN_ON
            header("Content-type:text/html;charset=utf-8");
            $order_id = I('id/d'); // 订单id
            $num = I('num/d'); // 数量
            $setmeal = I('setmeal/d'); // 套餐id
            $paypwd = I('paypwd'); // 支付密码
            if(!session('user')) $this->error('请先登录',U('User/login'));
            // 修改订单的支付方式
//            $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
//            M('order')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
            $order = M('order')->where("order_id", $order_id)->find();
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            // dump($user);exit;
            if($order['pay_status'] == 1){
                $this->error('此订单，已完成支付!');
            }
            if(encrypt($paypwd) != $user['paypwd']){
                $this->error('安全密码不正确!');
            }

        if(empty($order_id)){
                if(empty($num) || empty($setmeal)){
                    $this->error('异常操作!');
                }
                $setmeal = Db::name('goods_setmeal')->alias('s')->join('goods g','g.goods_id = s.goods_id')->where('id',$setmeal)->find();

                $order_num = Db::name('order')->alias('o')->join('order_goods g','o.order_id = g.order_id')
                    ->where("user_id = {$user['user_id']} AND setmeal_id = {$setmeal['id']} AND type = 0")->field('sum(goods_num) sums')->find();
                if($setmeal['stock'] < $num){
                    $this->error('库存数量不足!');
                }

                if($order_num['sums'] + $num > $setmeal['limit_num']){
                    $this->error('每人限购'.$setmeal['limit_num'].'件!');
                }
                $order['order_amount'] = $setmeal['trade_price'] * $num;
                $order['quota'] = $setmeal['quota'] * $num;
                $order['type'] = 0;
        }else{
            if($order['user_id'] != $user['user_id']){
                    $this->error('异常操作!');
                }
            }
        if($order['order_amount']>$user['user_money']){
            $this->error('收益积分不足,付款失败!');
        }
        //批发
        if($order['type'] === 0){
            //配额
            if($order['quota']>$user['frozen_money']){
                $this->error('配额不足,付款失败!');
            }
        }
        //自营
        if($order['type'] == 2){
            //兑换积分
            if($order['shop_integral']>$user['distribut_money']){
                $this->error('兑换积分不足,付款失败!');
            }
        }
        //获取配置
        $system = tpCache('ylg_spstem_role');
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        //批发和零售进入队列
        if ($order['type'] == 1) {
            $redis->lpush('order', $order_id);
        }
        if ($order['type'] == 0) {
            $redis->lpush('orders', $setmeal['id']);
        }
        if ($order['type'] == 0) {
            //批发
            $this->wholesale($order, $setmeal, $num, $user);
        } elseif ($order['type'] == 1) {
            //零售
            $this->retail($system, $user);

        } else {
            //自营
            $this->ordinary($order, $order_id, $user);

        }
        
    }

    /**
     *  服务订单提交支付方式
     */
    public function getCodeServer(){
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); // 订单id
        if(!session('user')) $this->error('请先登录',U('User/login'));
        // 修改订单的支付方式
        $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
        M('repair_order')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
        $order = M('repair_order')->where("order_id", $order_id)->find();
        if($order['pay_status'] == 1){
            $this->error('此订单，已完成支付!');
        }
        //订单支付提交
        $pay_radio = 'pay_code=weixin';
        $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $payBody = '测试商品';
        $config_value['body'] = $payBody;
        //微信JS支付  && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')
        if($this->pay_code == 'weixin' && $_SESSION['openid']){
            $code_str = $this->payment->getJSAPI($order);
            exit($code_str);
        }else{
            $code_str = $this->payment->getJSAPI($order);
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        exit;
        return $this->fetch('payment');  // 分跳转 和不 跳转
    }

    public function getPay(){
        //手机端在线充值
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); //订单id
        $user = session('user');
        $data['account'] = I('account');
        if($order_id>0){
            M('recharge')->where(array('order_id'=>$order_id,'user_id'=>$user['user_id']))->save($data);
        }else{
            $data['user_id'] = $user['user_id'];
            $data['nickname'] = $user['nickname'];
            $data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
            $data['ctime'] = time();
            $order_id = M('recharge')->add($data);
        }
        if($order_id){
            $order = M('recharge')->where("order_id", $order_id)->find();
            if(is_array($order) && $order['pay_status']==0){
                $order['order_amount'] = $order['account'];
                $pay_radio = $_REQUEST['pay_radio'];
                $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
                $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
                M('recharge')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
                //微信JS支付
                if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
                    $code_str = $this->payment->getJSAPI($order);
                    exit($code_str);
                }else{
                    $code_str = $this->payment->get_code($order,$config_value);
                }
            }else{
                $this->error('此充值订单，已完成支付!');
            }
        }else{
            $this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        return $this->fetch('recharge'); //分跳转 和不 跳转
    }
        public function notifyUrl(){
            $this->payment->response();
            exit();
        }

        public function returnUrl(){
            $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';
            if(stripos($result['order_sn'],'recharge') !== false)
            {
                $order = M('recharge')->where("order_sn", $result['order_sn'])->find();
                $this->assign('order', $order);
                if($result['status'] == 1)
                    return $this->fetch('recharge_success');
                else
                    return $this->fetch('recharge_error');
                exit();
            }
            $order = M('order')->where("order_sn", $result['order_sn'])->find();
            //预告所获得积分
            $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");


            $this->assign('order', $order);
            $this->assign('point',$points);
            if($result['status'] == 1)
                return $this->fetch('success');
            else
                return $this->fetch('error');
        }
    // 批发
    public function wholesale($order, $setmeal, $num,$user)
    {

        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        if ($res = $redis->rpop('orders')) {
            Db::startTrans();
            $setmeal = Db::name('goods_setmeal')->alias('s')->join('goods g', 'g.goods_id = s.goods_id')->where('id', $res)->find();
            if ($setmeal['stock'] < $num) {
                $this->error('库存数量不足!');
            }
            $address = M('UserAddress')->where("user_id", $user['user_id'])->find();
            if (empty($address)) {
                $this->error('请添加收货地址!');
            }
            //扣除用户需要扣得金额
            $result = Db::name('users')->where('user_id', $user['user_id'])->Dec('user_money', $order['order_amount'])->Dec('frozen_money', $order['quota'])->update();
            if ($result) {
                Db::name('goods_setmeal')->where('id', $setmeal['id'])->Dec('stock', $num)->update();
                $setmeal['goods_num'] = $num;
                $setmeal['goods_price'] = $setmeal['trade_price'];
                $setmeal['setmeal_id'] = $setmeal['id'];
                $order_goods = array('0' => $setmeal);
                $orderLogic = new OrderLogic();
                $orderLogic->setAction("buy_now");
                $orderLogic->setCartList($order_goods);

                $car_price['goodsFee'] = $setmeal['trade_price'];
                $car_price['quota'] = $order['quota'];
                $car_price['balance'] = $order['order_amount'];
                $car_price['balance'] = $order['order_amount'];
                $car_price['pointsFee'] = 0;
                $car_price['order_prom_id'] = 0;
                $car_price['order_prom_amount'] = 0;
                $car_price['payables'] = $order['order_amount'];
                // 添加订单
                $result = $orderLogic->addOrder($user['user_id'], 0, $address['address_id'], 0, 0, 0, $car_price, 0, 0, 0, $start_server_time = 0, $end_server_time = 0, 0);
                if ($result['status'] == 1) {
                    $order_id = $result['result'];
                    $result = 1;
                } else {
                    $result = 0;
                }
            }
            //更改订单状态
            $results = Db::name('order')->where("order_id = {$order_id}")->save(['pay_status' => 1, 'pay_time' => time()]);
            if ($result && $results) {
                //余额日志
                $log = balancelog($order_id, $user['user_id'], -$order['order_amount'], 8, $user['user_money'], $user['user_money'] - $order['order_amount'], $order_id);
                //配额日志
                $logs = integrallog($order_id, $user['user_id'], -$order['quota'], 8, $user['frozen_money'], $user['frozen_money'] - $order['quota']);
                if ($log && $logs) {
                    Db::commit();
                    $url='/Mobile/Order/order_list';
                    if ($order['type'] == 0) {
                        $url='/Mobile/Member/buy_list';
                        $this->success('支付成功!', $url);
                    } else {
                        $this->success('支付成功!', $url);
                    }
                } else {
                    Db::rollback();
                    $this->error('生成日志失败!');
                }
            } else {
                Db::rollback();
                $this->error('支付失败!');
            }
        }
    }

    //零售
    public function retail($system,$user)
    {
         $redis = new \Redis();
         $redis->connect('127.0.0.1', 6379);
        if ($res = $redis->rpop('order')) {
            Db::startTrans();
            $order = Db::name('order')->alias('o')->join('order_goods g', 'g.order_id = o.order_id')->where("o.order_id = $res")->find();
            $rorder = Db::name('goods')->where("goods_id = {$order['goods_id']}")->find();
            if ($order['goods_num'] > $rorder['store_count']) {
                $this->error('商品库存不足请稍后支付！');
            }
            //零售
            $result = Db::name('users')->where('user_id', $user['user_id'])->dec('user_money', $order['order_amount'])->inc('frozen_money', $order['goods_price'] * $system['invite_integral'])->update();
            if ($result) {
                $order_goods = Db::name('order_goods')->where('order_id',$order['order_id'])->field('goods_id,goods_num,goods_price')->find();
                //获取代理商出售的商品
                if(!empty($user['second_leader'])){
                    $consignment = Db::name('goods_consignment')->alias('g')->where("g.user_id in ({$user['second_leader']}) AND g.goods_id = {$order_goods['goods_id']} and g.surplus_num>0")->order('g.id,g.user_id desc')->field('g.*')->select();
                    $surplus_num = Db::name('goods_consignment')->alias('g')->where("g.user_id in ({$user['second_leader']}) AND g.goods_id = {$order_goods['goods_id']} and g.surplus_num>0")->order('g.id desc')->field('g.*')->limit($order_goods['goods_num'])->sum('surplus_num');
                    /*if(empty($consignment[0]['goods_id'])){
                        unset($consignment);
                    }*/
                }
                if(!empty($consignment)){
                    foreach($consignment as $list2){
                        $sort[]=$list2["user_id"];
                    }
                    array_multisort($sort,SORT_DESC,$consignment);
                    if($surplus_num<$order_goods['goods_num']){
                        $consignment2 = Db::name('goods_consignment')->alias('g')->where("g.user_id not in ({$user['second_leader']}) and g.goods_id = {$order_goods['goods_id']} AND g.surplus_num>0")->field('sum(g.surplus_num) surplus_nums,g.*')->order('create_time')->limit($order_goods['goods_num']-$surplus_num)->select();
                        $consignment=array_merge($consignment,$consignment2);
                    }

                }else{
                    $consignment = Db::name('goods_consignment')->where("goods_id = {$order_goods['goods_id']} AND surplus_num>0")->order('create_time')->limit($order_goods['goods_num'])->select();

                }
                $sum = 0; // 累计数量
                minus_stock($order);
                //返寄售收入
                for($i = 0;$i<count($consignment);$i++){
                        // 9                            10
                        if($order_goods['goods_num'] >= $consignment[$i]['surplus_num']+$sum){
                            $data['surplus_num'] = 0;  //商品剩余数量
                        }else{
                            $data['surplus_num'] = $consignment[$i]['surplus_num']+$sum-$order_goods['goods_num'];
                        }
                        $sum+=$consignment[$i]['surplus_num'];// 累计数量
//                        $data['goods_id'] = $consignment[$i]['id'];  //代理出售商品表id
                        $data['update_time'] = date('Y-m-d H:i:s');  //修改时间
                        $agentdata['agent_id'] = $consignment[$i]['user_id']; // 代理商id  更改代理商金额
                        $agentdata['order_id'] = $consignment[$i]['order_id'];      // 订单ID
                        $agentdata['setmeal_id'] = $consignment[$i]['setmeal_id'];      // 订单ID
                        $agentdata['sell_num'] = $consignment[$i]['surplus_num'] - $data['surplus_num'] ; // 成交数量
                        $agentdata['create_time'] = date('Y-m-d H:i:s'); // 成交时间
                        $conuser = Db::name('users')->where("user_id = {$consignment[$i]['user_id']}")->find();
                        //代理商收入
                        $money = $order_goods['goods_price']*$agentdata['sell_num']*(1-$system['handling_fee']);
                        //赠送代理商兑换积分
                        $goods_integral = $order_goods['goods_price']*$agentdata['sell_num']*$system['goods_integral'];
                        //寄售商品收入
                        $res = Db::name('users')->where('user_id',$consignment[$i]['user_id'])->Inc('user_money',$money)->Inc('distribut_money',$goods_integral)->update();
                        if(!$res){
                            $this->error('支付失败,请联系技术人员');
                        }
                        //代理订单表
                        $agentid = Db::name('agent_order')->insertGetId($agentdata);
                        //代理寄售表
                        Db::name('goods_consignment')->where("user_id = {$agentdata['agent_id']} AND id = {$consignment[$i]['id']}")->update($data);
                        //余额变动日志
                        $log = balancelog($agentid,$consignment[$i]['user_id'],$money,11,$conuser['user_money'],$conuser['user_money']+$money);
                        $logs = shoppinglog($res,$consignment[$i]['user_id'],$goods_integral,9,$conuser['distribut_money'],$conuser['distribut_money']+$order['goods_price']*$system['goods_integral']);
                        //剩余数量
                        if($sum >= $order_goods['goods_num']){
                            break;
                        }
                    }
            }
            $results = Db::name('order')->where("order_id = {$res}")->save(['pay_status' => 1, 'pay_time' => time()]);
            if ($result && $results) {
                //余额日志
                //消费专区 专用累计业绩
                $log = balancelog($res, $user['user_id'], -$order['order_amount'], 15, $user['user_money'], $user['user_money'] - $order['order_amount'], $res);
                //送购物积分
                $logs = integrallog($res, $user['user_id'], $order['order_amount'] * $system['invite_integral'], 9, $user['frozen_money'], $user['frozen_money'] + $order['order_amount'] * $system['invite_integral']);;

                if ($log && $logs) {
                    Db::commit();
                    $url='/Mobile/Order/order_list';
                    if ($order['type'] == 0) {
                        $this->success('支付成功!', $url);
                    } else {
                        $this->success('支付成功!', $url);
                    }
                } else {
                    Db::rollback();
                    $this->error('生成日志失败!');
                }
            } else {
                Db::rollback();
                $this->error('支付失败!');
            }
        }
    }


//普通区  自营区
    public function ordinary($order, $order_id, $user)
    {
        Db::startTrans();
        //自营
        $result = Db::name('users')->where("user_id = {$user['user_id']}")->dec('user_money',$order['order_amount'])->dec('distribut_money',$order['shop_integral'])->update();
        $results = Db::name('order')->where("order_id = {$order_id}")->save(['pay_status' => 1, 'pay_time' => time()]);
        if ($result && $results) {
            //余额日志
            $log = balancelog($order_id, $user['user_id'], -$order['order_amount'], 8, $user['user_money'], $user['user_money'] - $order['order_amount'], $order_id);
            $logs = shoppinglog($order_id, $user['user_id'], -$order['shop_integral'], 8, $user['distribut_money'], $user['distribut_money'] - $order['shop_integral']);
            if ($log && $logs) {
                Db::commit();
                $url='/Mobile/Order/order_list';
                if ($order['type'] == 0) {
                    $this->success('支付成功!', $url);
                } else {
                    $this->success('支付成功!', $url);
                }
            } else {
                Db::rollback();
                $this->error('生成日志失败!');
            }
        } else {
            Db::rollback();
            $this->error('支付失败!');
        }
    }
}
