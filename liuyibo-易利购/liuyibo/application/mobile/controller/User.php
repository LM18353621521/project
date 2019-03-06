<?php

namespace app\mobile\controller;

use app\common\logic\CartLogic;
use app\common\logic\DistributLogic;
use app\common\logic\MessageLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\logic\CouponLogic;
use app\common\logic\JssdkLogic;
use app\common\model\Order;
use app\common\model\Users;
use think\Page;
use think\Request;
use think\Verify;
use think\db;

class User extends MobileBase
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
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express' , 'bind_guide', 'bind_account',
        );
        $is_bind_account = tpCache('basic.is_bind_account');

        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {

                header("location:" . U('Mobile/User/login'));

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

    /*
     * 用户中心首页
     */
    public function index()
    {
        // Session::clear();
        $user_id =$this->user_id;
        $logic = new UsersLogic();
        $user = $logic->get_info($user_id); //当前登录用户信息
        $comment_count = M('comment')->where("user_id", $user_id)->count();   // 我的评论数
        $level_name = M('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();

        // 获取当前用户下的两个等级的粉丝id
//        $list = M('users')->field('user_id')->whereOR(['first_leader'=>$user_id])->whereOR(['second_leader'=>$user_id])->select();
//        $user_arr = array_column($list, 'user_id');
//        // 查询出当前粉丝  已经购买过的粉丝人数
//        if (empty($user_arr))
//            $purchase = 0;
//        else
//            $purchase=Db::name('order')->where('user_id','IN',$user_arr)->where('order_status','in',['2','4'])->where('shipping_status&pay_status',1)->group('user_id')->count();
//        $this->assign('purchase_count', $purchase);
//        dump($user['result']);exit;
        $frozen_money = Db::name('users')->where(['user_id' => $user_id])->value('frozen_money');
        $mobile = Db::name('users')->where(['user_id' => $user['result']['first_leader']])->value('mobile');
        if($mobile){
            $mobile = substr_replace($mobile,'****',3,4);
        }
        //获取团队（所有下线）
        $userids2=get_team($user_id,2);
        $ids2=implode(',',$userids2);

        if(empty($ids2)){
            $team_performance=0;
        }else{
            $team_performance=Db::name('users')->where(['user_id'=>['in',$ids2]])->sum('pay_points');
        }
        
        $this->assign('team_performance', $team_performance);
        $this->assign('mobile',$mobile);
        $this->assign('frozen_money', $frozen_money);
        $this->assign('user_message_count', $user_message_count);
        $this->assign('level_name', $level_name);
        $this->assign('comment_count', $comment_count);
        $this->assign('user',$user['result']);
        $this->assign('title','个人中心');
        return $this->fetch();
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    /*
     * 账户资金
     */
    public function account()
    {
        // 最近7天佣金记录
        $user_id = $this->user_id;
        $rebate_log = Db::name('rebate_log')->where("user_id=".$user_id." and status=3 ")->whereTime('confirm_time','w')->field("*,FROM_UNIXTIME(confirm_time,'%Y-%m-%d %H:%i:%s') as confirmTime")->order('id desc')->select();
        // --------------  

        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        $this->assign('rebate_log', $rebate_log);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    //收益积分明细 
    //1：充值；2：申请提现；3：提现失败返还 4：直推下级业绩返点 5：代理商补贴  6 : 直推代理商收入 7：月销售额收入；8：代理专区购买；9：转出；10：转入； 11：寄售商品收入 12：合伙人返点1；13：转让手续费 14 : 回收出售商品；15消费专区收益积分购买；16：代理商团队奖；17：董事团队奖：18：兑换专区退款；19：个人所得税；20合伙人返点2；21：直推返点；22：兑换专区购买
    public function account_list()
    {
        $user_id=$this->user_id;
        $page = (int)I('p') ? (int)I('p') : 0;
        $list = 15;
        $account_log = M('balancelog')->where("userId=" . $user_id.' and type<>19')->order('id desc')->limit($page*$list, $list)->select();
        $logs=$account_log;
        foreach ($logs as $k=>$v) {
            $logs[$k]['createTime'] = date('Y-m-d',strtotime($v['createTime']));
            switch ($v['type']) {
                case 1 :
                    $logs[$k]['type_str'] = "充值";
                    break;
                case 2 :
                    $logs[$k]['type_str'] = "申请提现";
                    break;
                case 3 :
                    $logs[$k]['type_str'] = "提现失败返还";
                    break;
                case 4 :
                    $logs[$k]['type_str'] = "直推下级业绩返点";
                    break;
                case 5 :
                    $logs[$k]['type_str'] = "代理商补贴";
                    break;
                case 6 :
                    $logs[$k]['type_str'] = "直推代理商收入";
                    break;
                case 7 :
                    $logs[$k]['type_str'] = "月销售额收入";
                    break;
                case 8 :
                    $logs[$k]['type_str'] = "代理专区购买";
                    break;
                case 9 :
                    $logs[$k]['type_str'] = "转出";
                    break;
                case 10 :
                    $logs[$k]['type_str']= "转入";
                    break;
                case 11 :
                    $logs[$k]['type_str']= "寄售商品收入";
                    break;
                case 12 :
                    $logs[$k]['type_str'] = "合伙人补贴";
                    break;
                case 13 :
                    $logs[$k]['type_str'] = "转让手续费";
                    break;
                case 14 :
                    $logs[$k]['type_str'] = "回收出售商品";
                    break;
                case 15 :
                    $logs[$k]['type_str'] = "消费专区购买";
                    break;
                case 16 :
                    $logs[$k]['type_str'] = "代理商团队奖";
                    break;
                case 17 :
                    $logs[$k]['type_str'] = "董事团队奖";
                    break;
                case 18 :
                    $logs[$k]['type_str'] = "兑换专区退款";
                    break;
                case 19 :
                    $logs[$k]['type_str'] = "个人所得税";
                    break;
                case 20 :
                    $logs[$k]['type_str'] = "合伙人返点2";
                    break;
                case 21 :
                    $logs[$k]['type_str'] = "直推返点".'('.$v['reflectId'].')';
                    break;
                case 22 :
                    $logs[$k]['type_str'] = "兑换专区购买";
                    break;
                default:
                    $logs[$k]['type_str'] = "收入";
                    break;
            }
        }
        if(IS_POST){
            $this->ajaxReturn($logs);
        }
        return $this->fetch();
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        $this->assign('detail',$detail);
        return $this->fetch();
    }

    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach($data['result'] as $k =>$v){
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if($user_type==1){ //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id'=>$v['cid']])->getField('goods_id');
            }
            if($user_type==2){ //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id'=>$v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
//
//            header("Location: " . U('Mobile/User/index'));
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        $this->assign('referurl', $referurl);
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
        $password = trim(I('post.password'));
        //验证码验证
        if (isset($_POST['verify_code'])) {
            $verify_code = I('post.verify_code');
            $verify = new Verify();
            if (!$verify->check($verify_code, 'user_login')) {
                $res = array('status' => 0, 'msg' => '验证码错误');
                exit(json_encode($res));
            }
        }
        $logic = new UsersLogic();
        $res = $logic->login($username, $password);

        if ($res['status'] == 1) {
            //当天登录触发收货
            if(date('Y-m-d',$res['result']['last_login'])!=date('Y-m-d')){
                $this->confirm_order();
            }
            M('users')->where("mobile",$username)->setField('last_login',time());

            $res['url'] = urldecode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
            
            // lzz 冻结资金提现处理
            $prize = new \app\common\logic\DistributPrizeLogic();
            $prize->frozen_money($res['result']['user_id']);
        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

        if($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            if (!empty(session("reg")) && time() - session("reg") <= 5) {
                $this->ajaxReturn(['msg'=>'系统繁忙']);
            }
            session("reg", time());
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $paypwd = I('post.paypwd', '');
            $paypwd2 = I('post.paypwd2', '');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();
            if($password!=$password2){
                $this->ajaxReturn(['msg'=>'两次登录密码不一致']);
            }
            if($paypwd!=$paypwd2){
                $this->ajaxReturn(['msg'=>'两次安全密码不一致']);
            }
            if($password==$paypwd){
                $this->ajaxReturn(['msg'=>'登录密码和安全密码不能相同']);
            }
            //是否开启注册验证码机制
            /*if(check_mobile($username)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }*/
            $count = Db::name("users")->where('mobile',$username)->count();
            //账号已存在
            if (!empty($count)) {
                $this->ajaxReturn(['msg'=>'账号已存在']);
            }
            $first_leader = I('invite');
            if (!empty($first_leader)) {
                //判断推荐人账户是否存在
                $parent = Db::name("users")
                    ->where('mobile',$first_leader)
                    ->whereOr('user_id','=',$first_leader)
                    ->find();
                //填写的推荐人不存在
                if (empty($parent)) {
                    $this->ajaxReturn(['msg'=>'推荐人不存在！']);
                }
                $data['first_leader'] = $parent['user_id'];
                if(!empty($parent['second_leader'])){
                    $data['second_leader']=$parent['second_leader'].','.$parent['user_id'];
                }else{
                    $data['second_leader']=$parent['user_id'];

                }
            }else{
                $this->ajaxReturn(['msg'=>'推荐人不能为空！']);
            }

            //昵称
            $data['nickname'] = $username;

            //账户
            if (empty($username)) {
                $this->ajaxReturn(['msg'=>'手机号不能为空！']);
            } else {
                //是否存在
                $account_occupy = M("users")->field("user_id")->where(array("mobile"=>$username))->find();
                if ($account_occupy) $this->ajaxReturn(['msg'=>'手机号已存在！']);
            }
            $data['mobile'] = $username;
            //登录密码
            if (empty($password)) {
                $this->ajaxReturn(['msg'=>'请填写登录密码！']);
            } else {
                if (strlen($password) < 6) {
                    $this->ajaxReturn(['msg'=>'登录密码长度最少为6位！']);
                }
            }
            $data['password'] = encrypt($password);;

            //安全密码
            if (empty($paypwd)) {
                $this->ajaxReturn(['msg'=>'请填写安全密码！']);
            } else {
                if (strlen($paypwd) < 6) {
                    $this->ajaxReturn(['msg'=>'安全密码长度最少为6位！']);
                }
            }
            $data['paypwd'] = encrypt($paypwd);

            //创建时间
            $data["reg_time"] = time();
            //会员等级
            $data["level"] = 1; 

            //创建用户表users数据
            Db::startTrans();
            $result = M("users")->add($data);

            if ($result) {
                //获取团队所有人
                $list=get_team($parent['user_id'],1);
                foreach ($list as $key => $value) {
                    //升级
                    user_upgrade($value);
                }

                Db::commit();
                $this->ajaxReturn(['msg'=>'注册成功！','status'=>1]);
            } else {
                Db::rollback();
                $this->ajaxReturn(['msg'=>'注册失败！']);
            }
            /*$logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname = I('post.nickname', '');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();

            //是否开启注册验证码机制
            if(check_mobile($username)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            //是否开启注册邮箱验证码机制
            if(check_email($username)){
                if($reg_smtp_enable){
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }

            $invite = I('invite');
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
            }else{
                $invite = array();
            }

            if($is_bind_account && session("third_oauth")){ //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite ,$nickname , $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            }else{
                $data = $logic->reg($username, $password, $password2,0,$invite);
            }


            if ($data['status'] != 1) $this->ajaxReturn($data);
            //注册成功，准备验证升级角色,此处是注册的地方----------标记<QualificationLogic>
            $qualificationLogic = new \app\common\logic\QualificationLogic();
            $qualificationLogic->prepare_update_level($data['result']['user_id']);
            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;*/
        }
        $this->assign('id',I('id')); // 邀请人id：
        $this->assign('regis_sms_enable',$reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable',$reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    public function bind_guide(){
        $data = session('third_oauth');
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);

        return $this->fetch();
    }

    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        if(IS_POST){
            $data = I('post.');
            $userLogic = new UsersLogic();
            $user['mobile'] = $data['mobile'];
            $user['password'] = encrypt($data['password']);
            $res = $userLogic->oauth_bind_new($user);
            if ($res['status'] == 1) {
                //绑定成功, 重新关联上下级
                $map['first_leader'] = cookie('first_leader');  //推荐人id
                // 如果找到他老爸还要找他爷爷他祖父等
                if($map['first_leader']){
                    $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
                    if($first_leader){
                        $map['second_leader'] = $first_leader['first_leader'];
                        $map['third_leader'] = $first_leader['second_leader'];
                    }
                    //他上线分销的下线人数要加1
                    M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
                }else
                {
                    $map['first_leader'] = 0;
                }
                $ruser = $res['result'];
                M('Users')->where('user_id' , $ruser['user_id'])->save($map);

                $res['url'] = urldecode(I('post.referurl'));
                $res['result']['nickname'] = empty($res['result']['nickname']) ? $res['result']['mobile'] : $res['result']['nickname'];
                setcookie('user_id', $res['result']['user_id'], null, '/');
                setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
                setcookie('uname', urlencode($res['result']['nickname']), null, '/');
                setcookie('head_pic', urlencode($res['result']['head_pic']), null, '/');
                setcookie('cn', 0, time() - 3600, '/');
                //获取公众号openid,并保持到session的user中
                $oauth_users = M('OauthUsers')->where(['user_id'=>$res['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
                $oauth_users && $res['result']['open_id'] = $oauth_users['open_id'];
                session('user', $res['result']);
                $cartLogic = new CartLogic();
                $cartLogic->setUserId($res['result']['user_id']);
                $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
                $userlogic = new OrderLogic();//登录后将超时未支付订单给取消掉
                $userlogic->setUserId($res['result']['user_id']);
                $userlogic->abolishOrder();
                return $this->success("绑定成功", U('Mobile/User/index'));
            }else{
                return $this->error("绑定失败,失败原因:".$res['msg']);
            }
        }else{
            return $this->fetch();
        }
    }
    public function express()
    {
        $order_id = I('get.order_id/d', 195);
        $order_goods = M('order_goods')->where("order_id", $order_id)->select();
        $delivery = M('delivery_doc')->where("order_id", $order_id)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('delivery', $delivery);
        return $this->fetch();
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);

        // ------ 手机号中间4位数用*代替 -------
        foreach($address_lists as $k=>$v){
            $address_lists[$k]['mobile'] = substr_replace($v['mobile'],'****',3,4);
        }
        // ------ END 18-7-4 -------

        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        if (IS_POST) {
            $source = input('source');
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, $post_data);
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $order_id = input('order_id/d');
            $action = input('action');

            $setmeal_id = input('setmeal_id');
            $consignment_id = input('consignment_id');


            if ($data['status'] != 1){
                $this->error($data['msg']);
            } elseif ($source == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action,'setmeal_id'=>$setmeal_id,'action'=>$action));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {
                $data['url']=U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url']=U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num'],'action'=>$action));
                $this->ajaxReturn($data);
            } elseif($_POST['source'] == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id,'action'=>$action));
                $this->ajaxReturn($data);
            } elseif($_POST['source'] == 'mention_goods'){
                $data['url']= U('/Mobile/Member/mention_goods', array('address_id' => $data['result'],'id'=>$goods_id,'consignment_id'=>$consignment_id,'action'=>$action));
                $this->ajaxReturn($data);
            }elseif($_POST['source'] == 'cart4'){
                $data['url']= U('/Mobile/Cart/cart4', array('address_id' => $data['result'],'setmeal'=>$setmeal_id,'num'=>$goods_num));
                $this->ajaxReturn($data);
            }else{
                $data['url']= U('/Mobile/User/address_list');
                $this->success($data['msg'], U('/Mobile/User/address_list'));
            }

        }

        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        //return $this->fetch('edit_address');
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $source = input('source');
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $action = input('action');
            $order_id = input('order_id/d');
            $setmeal_id = input('setmeal_id');
            $consignment_id = input('consignment_id');
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            if ($post_data['source'] == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action,'setmeal_id'=>$setmeal_id));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {
                $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($_POST['source'] == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                $this->ajaxReturn($data);
            } elseif($_POST['source'] == 'mention_goods'){
                $data['url']= U('/Mobile/Member/mention_goods', array('address_id' => $data['result'],'id'=>$goods_id,'consignment_id'=>$consignment_id));
                $this->ajaxReturn($data);
            } else{
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }


    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }

    public function set_default1()
    {
        $id = I('get.id/d');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if (!$row)
            $this->error('设置默认地址失败', U('User/address_list'));
        else
            $this->success("设置默认地址成功", U('User/address_list'));
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
                $dir = 'public/upload/head_pic/';
                if (!($_exists = file_exists($dir))){
                    $isMk = mkdir($dir, 777,true);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if($info){
                    $post['head_pic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
                }else{
                    $this->error($file->getError());//上传错误提示错误信息
                }
            }
            I('post.address') ? $address = I('post.address') : false; //昵称
            I('post.img_header') ? $action = I('post.img_header') : false; //昵称
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.wechat') ? $post['wechat'] = I('post.wechat') : false; //微信号
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机
            if ($post['sex']){
                switch ($post['sex']) {
                    case '保密':
                        $post['sex'] = 0;
                        break;
                    case '男':
                        $post['sex'] = 1;
                        break;
                    case '女':
                        $post['sex'] = 2;
                        break;
                }
            }
            if ($post['province']){
                unset($post['sex']);
            }
            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && exit(json_encode(['code'=>-1,'msg'=>'邮箱已被使用']));
                //$this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && exit(json_encode(['code'=>-1,'msg'=>'手机已被使用']));
                //$this->error("手机已被使用");
                if (!$code)
                    exit(json_encode(['code'=>-1,'msg'=>'请输入验证码']));
                    //$this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                exit(json_encode(['code'=>-1,'msg'=>$check_code['msg']]));
//                    $this->error($check_code['msg']);
            }
            if(I('bank')==1){
                $arr['bank_code']=I('bank_code')?:'';
                $arr['bank_num']=I('bank_num')?:'';
                $arr['bank_name']=I('bank_name')?:'';
                $post['wechat']=serialize($arr);
            }
            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            //$this->success("操作成功");
            //echo("<script>alert('提示内容')</script>");
            //$this->assign('msg', '修改成功');
            $this->redirect('User/userinfo');
            ($action == false) ? exit(json_encode(['code'=>1,'msg'=>'操作成功'])) : $this->success("操作成功");
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $user_province = M('region')->where(array('id' => $user_info['province'], 'level' => 1))->value('name');
        $user_city = M('region')->where(array('id' => $user_info['city'], 'level' => 2))->value('name');
        $user_address = $user_province.$user_city;
        if($user_info['wechat']){
            $email=unserialize($user_info['wechat']);
            $this->assign('email', $email);
        }
        $this->assign('user_address', $user_address);
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /*
     * 银行卡页面
     */
    public function bank_details (){
        $user_id = $this->user_id;
        $all_card_where = array(
            'user_id' => $user_id
        );
        $all_card = M('bank_card')->where($all_card_where)->select();

        //注释银行卡部分数字
        if($all_card){
            foreach($all_card as $k=>$vo){
                $all_card[$k]['bank_card'] = substr_replace($vo['bank_card'],'****','10','4');
            }
        }

        $this->assign('all_card',$all_card);
        return $this->fetch();
    }

    /*
     * 银行卡操作
    */
    public function add_bank_card(){
        C('TOKEN_ON', true);
        $bank_id = I('get.bank_id/d', 0);
        $bank_info = bank_information();//获取银行信息

        $locat = I('locat');

        if(IS_POST){
            $data = I('post.');

            if(!$this->verifyHandle('add_bank_card') && $data['act'] !== 'del'){
                $this->ajaxReturn(['status'=>0,'msg'=>'验证码错误']);
            }

            $data['user_id'] = $this->user_id;
            $data['bank_card'] = str_replace(' ', '', $data['bank_card']);
            $data['bank_card'] = implode(' ',str_split($data['bank_card'],4));
            $data['bank_name'] = bank_information($data['bank_name'],'name');


            if($data['act'] == 'edit' && $data['id']>0){
                $result = M('bank_card')->where(array('id'=>$data['id']))->save($data);
            }elseif($data['act'] == 'del' && $data['id']>0){
                $result = M('bank_card')->where(array('id'=>$data['id']))->delete();
            }else{
                $result = M('bank_card')->add($data);
            }

            if ($result !== false) {
                $url = $data['locat'] ? U('User/'.$data['locat']) : U('User/bank_details');

                $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>$url]);
                exit;
            } else {
                $this->ajaxReturn(['status'=>0,'msg'=>'操作失败,联系客服!']);
                exit;
            }
        }
        if($bank_id){
            $bank_where = array(
                'id' => $bank_id
            );
            $bank_data = M('bank_card')->where($bank_where)->find();
            $this->assign('bank_data',$bank_data);
            $this->assign('act','edit');
        }
        if($locat) $this->assign('locat',$locat);

        $this->assign('bank_info',$bank_info);
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
        $userLogic = new UsersLogic();
        if (IS_POST) {
            $mobile = input('mobile');
            $mobile_code = input('mobile_code');
            $scene = input('post.scene', 6);
            $validate = I('validate',0);
            $status = I('status',0);
            $c = Db::name('users')->where(['mobile' => mobile, 'user_id' => ['<>', $this->user_id]])->count();
            $c && $this->error('手机已被使用');
            if (!$mobile_code)
                $this->error('请输入验证码');
            $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', $this->session_id, $scene);
            if($check_code['status'] !=1){
                $this->error($check_code['msg']);
            }
            if($validate == 1 & $status == 0){
                $res = Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile'=>$mobile]);
                if($res){
                    $this->success('修改成功',U('User/userinfo'));
                }
                $this->error('修改失败');
            }
        }

        $view = !input('step') ? '' : 'setMobile2';
        $this->assign('status',$status);
        return $this->fetch($view);
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function zpcollect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        // dump($data);die;
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('post.goods_id');
        $user_id = $this->user_id;
        $res = M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete();
        // $res = '1';
        if ($res) {
            $data[] =  $data;
                        $return = [];
                        $return['status'] = 'success';
                        $return['message'] = '取消收藏成功';
                        $return['data'] = $data;
                        echo json_encode($return);
                        exit;
        } else {
            $data[] =  $data;
                $return = [];
                $return['status'] = 'error';
                $return['error'] = '取消收藏失败';
                $return['data'] = [];
                echo json_encode($return);
                exit;
        }
    }

    /*
     *清空收藏
     */
    public function cart_empty(){
        $user_id = $this->user_id;
        $res = M('goods_collect')->where(['user_id' => $user_id])->delete();
        // $res = '';
        if ($res) {
            $data[] =  $data;
                        $return = [];
                        $return['status'] = 'success';
                        $return['message'] = '清空收藏成功';
                        $return['data'] = $data;
                        echo json_encode($return);
                        exit;
        } else {
            $data[] =  $data;
                $return = [];
                $return['status'] = 'error';
                $return['error'] = '清空收藏失败';
                $return['data'] = [];
                echo json_encode($return);
                exit;
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if(!$this->verifyHandle('message')){
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }

    //兑换积分明细
    //1：收入；2：支出 ；3；直推返兑换积分；8：购买商品 9：寄售返兑换积分；18：兑换专区退款；
    public function points_list()
    {
        $page = (int)I('p') ? (int)I('p') : 0;
        $list = 15;
        $user_id=$this->user_id;
        $logs =  M('shoppinglog')->where("userId=" . $user_id)->order('id desc')->limit($page*$list, $list)->select();
        foreach ($logs as $k=>$v) {
            $logs[$k]['createTime'] = date('Y-m-d',strtotime($v['createTime']));
            switch ($v['type']) {
                case 1 :
                    $logs[$k]['type_str'] = "收入";
                    break;
                case 2 :
                    $logs[$k]['type_str'] = "支出";
                    break;
                case 3 :
                    $logs[$k]['type_str'] = "直推返点".'('.$v['reflectId'].')';
                    break;
                case 18 :
                    $logs[$k]['type_str'] = "兑换专区退款";
                    break;
                case 8 :
                    $logs[$k]['type_str'] = "购买商品";
                    break;
                case 9 :
                    $logs[$k]['type_str'] = "寄售返兑换积分";
                    break;
                case 4 :
                    $logs[$k]['type_str'] = "购物返兑换积分";
                    break;
                default:
                    $logs[$k]['type_str'] = "收入";
                    break;
            }
        }
        if(IS_POST){
            $this->ajaxReturn($logs);
        }
        return $this->fetch();
    }


    /*
     * 登录密码修改
     */
    public function password()
    {
        if (IS_POST) {
            $old_password =trim(I('post.old_password')) ;
            $password =trim(I('post.new_password')) ;
            $password2 = trim(I('post.confirm_password'));
            if(empty($password)){
                exit(['code' => -2, 'msg' => '新密码不能为空']);
            }
            if(empty($old_password)){
                exit(['code' => -2, 'msg' => '原密码不能为空']);
            }
            if ($password2 != $password) {
                exit(['code' => -2, 'msg' => '两次密码不一致']);
            }
            $user = M('users')->where("user_id", $this->user_id)->find();
            if($user['password']!=encrypt($old_password)){
                exit(['code' => -2, 'msg' => '原密码验证错误！']);
            }   
            M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($password)));
            exit(json_encode(['code' => 1, 'msg' => '修改成功']));
        }
        if  (session('pwdOutTime')){
            $smsOutTime =  session('pwdOutTime') - time();
            echo $smsOutTime;
            if ($smsOutTime >= 0 ){
                $this->assign('smsOutTime', $smsOutTime);
            }
        }
        $this->assign('mobile', session(user)['mobile']);
        return $this->fetch();
    }

    /*
     * 安全密码修改
     */
    public function password2()
    {
        if (IS_POST) {
            $old_password =trim(I('post.old_password')) ;
            $password =trim(I('post.new_password')) ;
            $password2 = trim(I('post.confirm_password'));
            if(empty($password)){
                exit(['code' => -2, 'msg' => '新密码不能为空']);
            }
            if(empty($old_password)){
                exit(['code' => -2, 'msg' => '原密码不能为空']);
            }
            if ($password2 != $password) {
                exit(['code' => -2, 'msg' => '两次密码不一致']);
            }
            $user = M('users')->where("user_id", $this->user_id)->find();
            if($user['paypwd']!=encrypt($old_password)){
                exit(['code' => -2, 'msg' => '原密码验证错误！']);
            }   
            M('users')->where("user_id", $user['user_id'])->save(array('paypwd' => encrypt($password)));
            exit(json_encode(['code' => 1, 'msg' => '修改成功']));
        }
        if  (session('pwdOutTime')){
            $smsOutTime =  session('pwdOutTime') - time();
            echo $smsOutTime;
            if ($smsOutTime >= 0 ){
                $this->assign('smsOutTime', $smsOutTime);
            }
        }
        $this->assign('mobile', session(user)['mobile']);
        return $this->fetch();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if (!$this->verifyHandle('forget')) {
                    $this->error("验证码错误");
                };
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field));
                    header("Location: " . U('User/find_pwd'));
                    exit;
                } else {
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        if (empty($check)) {
            header("Location:" . U('User/forget_pwd'));
        } elseif ($check['is_check'] == 0) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            if ($check['is_check'] == 1) {
                $user = M('users')->where("mobile", $check['sender'])->whereOr('email', $check['sender'])->find();
                M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($password)));
                session('validate_code', null);
                return $this->fetch('reset_pwd_sucess');
                exit;
            } else {
                $this->error('验证码还未验证通过', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' =>  60,
            'imageW' =>  300,
            'fontttf' => '5.ttf',
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }
    //充值
    public function recharge()
    {
        if(IS_POST){
            if (!empty(session("recharge")) && time() - session("recharge") <= 5) {
                $this->ajaxReturn(['msg'=>'系统繁忙']);
            }
            session("recharge", time());
            $file =request()->file('img');
            if($file){
                $info = $file->move(ROOT_PATH . 'public' . DS . 'upload/img');
                $data['pay_code'] ='public' . DS . 'upload/img\\'.$info->getSaveName();
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>"未上传收款凭证！"]);
            }
            if(I('account')<=0||empty(I('account'))){
                $this->ajaxReturn(['status'=>0,'msg'=>"充值金额要大于0"]);
            }
            $data['user_id']=$this->user_id;
            $data['nickname']=session('user.nickname');
            $data['account']=I('account');
            $data['ctime']=time();
            $data['pay_name']=I('pay_name');
            $res=M('recharge')->add($data);
            if($res){
                $this->ajaxReturn(['status'=>1,'msg'=>"申请成功"]);
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>"申请失败"]);
            }
        }
        
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('system', tpCache('ylg_spstem_role'));
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    public function recharge_list(){
        $recharge_log_where = ['user_id'=>$this->user_id];
        $page = (int)I('p') ? (int)I('p') : 0;
        $list = 15;
        $recharge_log = M('recharge')->where($recharge_log_where)
            ->order('order_id desc')
            ->limit($page*$list, $list)
            ->select();
        if (IS_POST) {
            $this->ajaxReturn($recharge_log);
        }
        return $this->fetch();
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {
        C('TOKEN_ON', true);
        $ylg_spstem_role = tpCache('ylg_spstem_role'); 
        $reserve_funds=$this->user['user_money']-$ylg_spstem_role['reserve_funds'];

        if($reserve_funds<0){
            $reserve_funds='0.00';
        }
        if (IS_POST) {

            /*if(!$this->verifyHandle('withdrawals')){
                $this->ajaxReturn(['status'=>0,'msg'=>'验证码错误']);
            };*/

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            
            $taxfee = $ylg_spstem_role['bill_charge'];

            $money=Db::name('withdrawals')->whereTime('create_time', 'today')->where('user_id',$this->user_id)->sum('money');
            if(($money+$data['money'])>$ylg_spstem_role['withdrawal_limit']){
                $this->ajaxReturn(['status'=>0,'msg'=>'每天最多可提现'.$ylg_spstem_role['withdrawal_limit']]);
            }

            $data['taxfee'] = $data['money']*$taxfee; //手续费

            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0,'msg'=>'支付密码错误']);
            }

            if ($data['money'] > $this->user['user_money']-$ylg_spstem_role['reserve_funds']) {
                $this->ajaxReturn(['status'=>0,'msg'=>"你最多可提现{$reserve_funds}账户余额."]);
                exit;
            }
            if (($data['money'] + $data['taxfee']) > $this->user['user_money']-$ylg_spstem_role['reserve_funds']) {
                $this->ajaxReturn(['status'=>0,'msg'=>"余额不足,无法支付手续费"]);
                exit;
            }

            // 扣钱 添加流水记录
            //accountLog($this->user_id, -$data['money'], 0, $desc = '提现扣除',  0, $res, '',12);

            // $withdrawal = M('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('money');
            // $taxfee_all = M('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('taxfee');
            // if ($this->user['user_money'] < ($withdrawal + $data['money'] + $taxfee_all + $taxfee)) {
            //     $this->ajaxReturn(['status'=>0,'msg'=>'您有提现申请待处理，本次提现余额不足']);
            // }

            $num=$data['money'] + $data['taxfee'];
            // 启动事务
            Db::startTrans();
            try{
                $res = Db::name('withdrawals')->insertGetId($data);
                Db::name('users')->where('user_id',$this->user_id)->setDec('user_money',$num);
                balancelog($res,$this->user_id, -$num, 2, $this->user['user_money'],$this->user['user_money']-$num);

                // 提交事务
                Db::commit();    
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
            if ($res) {
                $this->ajaxReturn(['status'=>1,'msg'=>"已提交申请",'url'=>U('User/withdrawals_list')]);
                exit;
            } else {
                $this->ajaxReturn(['status'=>0,'msg'=>'提交失败,联系客服!']);
                exit;
            }

        }

        //判断是否银行卡
//        $bank_data = M('bank_card')->where(array('user_id'=>$this->user_id))->count();
//        if(!$bank_data){
//            $this->redirect(U('Mobile/User/add_bank_card',array('locat'=>'withdrawals')));
//        }



        /*$bank_card = M('bank_card')->where(array('user_id'=>$this->user_id))->find();
        $bank_card_all = M('bank_card')->where(array('user_id'=>$this->user_id))->select();

        $bank_card['image'] = bank_information($bank_card['bank_name'],'image');//获取银行对应logo

        foreach($bank_card_all as $k=>$vo){
            $bank_card_all[$k]['image'] = bank_information($vo['bank_name'],'image');
        }*/

        /*$this->assign('bank_card_all',$bank_card_all);
        $this->assign('bank_card',$bank_card);*/
        if($this->user['wechat']){
            $wechat=unserialize($this->user['wechat']);
        }
        $this->assign('email', $wechat);//银行卡信息
        $this->assign('reserve_funds',$reserve_funds );//可提现金额
        $this->assign('user_money', $this->user['user_money']);    //用户余额
        $this->assign('handling_fee', $ylg_spstem_role['bill_charge']*100);    //手续费
        return $this->fetch();
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $page = (int)I('p') ? (int)I('p') : 0;
        $list = 15;
        $res = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit($page*$list, $list)->select();
        foreach ($res as $key => $value) {
            if($value['status']==1){
                $res[$key]['status']='提现成功';
            }elseif($value['status']==2){
                $res[$key]['status']='提现失败';
            }else{
                $res[$key]['status']='申请中';
            }
            $res[$key]['create_time']=date('Y-m-d',$value['create_time']);
        }
        if (IS_POST) {
            $this->ajaxReturn($res);
        }
        return $this->fetch();
    }
    /**
     * 转让
     */
    public function transfer()
    {
        $commodity = tpCache('ylg_spstem_role.commodity');
        if (IS_POST) {
            if (!empty(session("transfer")) && time() - session("transfer") <= 5) {
                $this->ajaxReturn(['msg'=>'系统繁忙']);
            }
            session("transfer", time());
            $reserve_funds=$this->user['user_money'];
            $data = I('post.');      
            if(empty($data['money'])||$data['money']<=0){
                $this->ajaxReturn(['status'=>0,'msg'=>'转让金额要大于0']);exit;
            }     
            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0,'msg'=>'安全密码错误']);exit;
            }

            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0,'msg'=>"你最多可转让{$reserve_funds}"]);exit;
            }
            
            if($reserve_funds<=0){
                $this->ajaxReturn(['status'=>0,'msg'=>'收益积分不足']);exit;
            }
            $user2 = M('users')->where("user_id", $data['user_name'])->whereOr("mobile", $data['user_name'])->find();
            if(empty($user2)){
                $this->ajaxReturn(['status'=>0,'msg'=>'收款用户不存在']);exit;
            }
            if($this->user_id==$user2['user_id']){
                $this->ajaxReturn(['status'=>0,'msg'=>'不能转给自己']);exit;
            }

            if(empty($user2['second_leader'])&&empty($this->user['second_leader'])){
                $this->ajaxReturn(['status'=>0,'msg'=>'只能转给团队的人']);exit;
            }

            if($this->user['second_leader']){
                $arr=explode(',',$this->user['second_leader']);
            }else{
                $arr=[];
            }

            if($user2['second_leader']){
                $arr2=explode(',',$user2['second_leader']);
            }else{
                $arr2=[];
            }

            if(in_array($user2['user_id'],$arr)||in_array($this->user_id,$arr2)){

            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>'只能转给团队的人']);exit;
            }
            if (($data['money']*(1+ $commodity) )> $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0,'msg'=>"余额不足,无法支付手续费"]);
                exit;
            }
            $num=$data['money'];

            $num2=$data['money']*$commodity;
            // 启动事务
            Db::startTrans();
            try{
                $data2=array(
                    "userId"=>$this->user['user_id'],
                    "account"=>$this->user['mobile'],
                    "toUserId"=>$user2['user_id'],
                    "money"=>$num,
                    "toUserAccount"=>$user2['mobile'],
                    "createTime"=>date('Y-m-d H:i:s')
                );
                $res=M("transfer")->add($data2);//添加转让
                //转出
                Db::name('users')->where('user_id',$this->user_id)->setDec('user_money',$num);
                balancelog($res,$this->user_id, -$num, 9, $this->user['user_money'],$this->user['user_money']-$num);

                if($num2>0){
                    //手续费
                    Db::name('users')->where('user_id',$this->user_id)->setDec('user_money',$num2);
                    balancelog($res,$this->user_id, -$num2, 13, $this->user['user_money']-$num,$this->user['user_money']-$num-$num2);
                }

                //转入
                Db::name('users')->where('user_id',$user2['user_id'])->setInc('user_money',$num);
                balancelog($res,$user2['user_id'], $num, 10, $user2['user_money'],$user2['user_money']+$num);

                // 提交事务
                Db::commit();  
                $this->ajaxReturn(['status'=>1,'msg'=>"转让成功",'url'=>U('User/transfer')]);
                exit;  
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                //dump($e);
                $this->ajaxReturn(['status'=>0,'msg'=>'转让失败']);
                exit;
            }
        }
        $this->assign('user_money', $this->user['user_money']);    //用户余额
        $this->assign('handling_fee', $commodity*100);    //手续费
        return $this->fetch();
    }
    /**
     * 转让列表
     */
    public function transfer_list()
    {
        $page = (int)I('p') ? (int)I('p') : 0;
        $list = 15;
        $withdrawals_where['userId'] = $this->user_id;
        $where['toUserId'] = $this->user_id;
        $list = M('transfer')->where($withdrawals_where)->whereOr($where)->order("id desc")->limit($page*$list, $list)->select();
        foreach ($list as $key => $value) {
            $list[$key]['createTime'] = date('Y-m-d',strtotime($value['createTime']));
        }
        $this->ajaxReturn(['data'=>$list,'user_id'=>$this->user_id]);
    }
    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {

        return $this->fetch();
    }

    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type');
        $user_logic = new UsersLogic();
        $message_model = new MessageLogic();
        if ($type === '0') {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();

        } else if ($type == 1){
            $user_sys_message = $message_model->getUserMessageNotice(3);
            foreach($user_sys_message as $k=>$v){
                $user_sys_message[$k]['data'] = unserialize($v['data']);
            }

            $this->assign('type',$type);
        }else if ($type == 2) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }

        $this->assign('messages', $user_sys_message);
        return $this->fetch('ajax_message_notice');

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


    /**
     * 设置消息通知
     */
    public function set_notice(){
        //暂无数据
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id','IN', $visit_ids)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if(strrchr($_SERVER['HTTP_REFERER'],'/') =='/cart2.html'){  //用户从提交订单页来的，后面设置完有要返回去
            session('payPriorUrl',U('Mobile/Cart/cart2'));
        }
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/userinfo',['action'=>'mobile']));
        $step = I('step', 1);
        if ($step > 1) {
            $check = session('validate_code');
            if (empty($check)) {
                $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }
    /**
     *  点赞
     * @author lxl
     * @time  17-4-20
     * 拷多商家Order控制器
     */
    public function ajaxZan()
    {
        $comment_id = I('post.comment_id/d');
        $user_id = $this->user_id;
        $comment_info = M('comment')->where(array('comment_id' => $comment_id))->find();  //获取点赞用户ID
        $comment_user_id_array = explode(',', $comment_info['zan_userid']);
        if (in_array($user_id, $comment_user_id_array)) {  //判断用户有没点赞过
            $result['success'] = 0;
        } else {
            array_push($comment_user_id_array, $user_id);  //加入用户ID
            $comment_user_id_string = implode(',', $comment_user_id_array);
            $comment_data['zan_num'] = $comment_info['zan_num'] + 1;  //点赞数量加1
            $comment_data['zan_userid'] = $comment_user_id_string;
            M('comment')->where(array('comment_id' => $comment_id))->save($comment_data);
            $result['success'] = 1;
        }
        exit(json_encode($result));
    }


    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign() {
        $user_id = $this->user_id;
        $config = tpCache('sign');
        if (IS_AJAX) {
            $date = I('str'); //20170929
            //是否正确请求
            (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => -1, 'msg' => '请求错误！', 'result' => date("Y-n-j", time())]);

            $integral = $config['sign_integral'];
            $msg = "签到赠送" . $integral . "积分";
            //签到开关
            if ($config['sign_on_off'] > 0) {
                $map['lastsign'] = $date;
                $map['user_id'] = $user_id;
                $check = DB::name('user_sign')->where($map)->find();
                $check && $this->ajaxReturn(['status' => -1, 'msg' => '您今天已经签过啦！', 'result' => '']);
                if (!DB::name('user_sign')->where(['user_id' => $user_id])->find()) {
                    //第一次签到
                    $data = [];
                    $data['user_id'] = $user_id;
                    $data['signtotal'] = 1;
                    $data['lastsign'] = $date;
                    $data['cumtrapz'] = $config['sign_integral'];
                    $data['signtime'] = "$date";
                    $data['signcount'] = 1;
                    $data['thismonth'] = $config['sign_integral'];
                    if (M('user_sign')->add($data)) {
                        $status = ['status' => 1, 'msg' => '签到成功！', 'result' => $config['sign_integral']];
                    } else {
                        $status = ['status' => -1, 'msg' => '签到失败!', 'result' => ''];
                    }
                    $this->ajaxReturn($status);
                } else {
                    $update_data = array(
                        'signtotal' => ['exp', 'signtotal+' . 1], //累计签到天数
                        'lastsign' => ['exp', "'$date'"], //最后签到时间
                        'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_integral']], //累计签到获取积分
                        'signtime' => ['exp', "CONCAT_WS(',',signtime ,'$date')"], //历史签到记录
                        'signcount' => ['exp', 'signcount+' . 1], //连续签到天数
                        'thismonth' => ['exp', 'thismonth+' . $config['sign_integral']], //本月累计积分
                    );

                    $daya = Db::name('user_sign')->where('user_id', $user_id)->value('lastsign');    //上次签到时间
                    $dayb = date("Y-n-j", strtotime($date) - 86400);                                   //今天签到时间
                    //不是连续签
                    if ($daya != $dayb) {
                        $update_data['signcount'] = ['exp', 1];                                       //连续签到天数
                    }
                    $mb = date("m", strtotime($date));                                               //获取本次签到月份
                    //不是本月签到
                    if (intval($mb) != intval(date("m", strtotime($daya)))) {
                        $update_data['signcount'] = ['exp', 1];                                      //连续签到天数
                        $update_data['signtime'] = ['exp', "'$date'"];                                  //历史签到记录;
                        $update_data['thismonth'] = ['exp', $config['sign_integral']];              //本月累计积分
                    }

                    $update = Db::name('user_sign')->where(['user_id' => $user_id])->update($update_data);

                    (!$update) && $this->ajaxReturn(['status' => -1, 'msg' => '网络异常！', 'result' => '']);

                    $signcount = Db::name('user_sign')->where('user_id', $user_id)->value('signcount');
                    $integral = $config['sign_integral'];
                    //满足额外奖励
                    if (( $signcount >= $config['sign_signcount']) && ($config['sign_on_off'] > 0)) {
                        Db::name('user_sign')->where(['user_id' => $user_id])->update([
                            'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_award']],
                            'thismonth' => ['exp', 'thismonth+' . $config['sign_award']]
                        ]);
                        $integral = $config['sign_integral'] + $config['sign_award'];
                        $msg = "签到赠送" . $config['sign_integral'] . "积分，连续签到奖励" . $config['sign_award'] . "积分，共" . $integral . "积分";
                    }
                }
                if ($config['sign_integral'] > 0 && $config['sign_on_off'] > 0) {
                    accountLog($user_id, 0, $integral, $msg);
                    $status = ['status' => 1, 'msg' => '签到成功！', 'result' => $integral];
                } else {
                    $status = ['status' => -1, 'msg' => '签到失败!', 'result' => ''];
                }
                $this->ajaxReturn($status);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => '该功能未开启！', 'result' => '']);
            }
        }
        $map = [];
        $map['us.user_id'] = $user_id;
        $field = [
            'u.user_id as user_id',
            'u.nickname',
            'u.mobile',
            'us.*',
        ];
        $join = [
            ['users u', 'u.user_id=us.user_id', 'left']
        ];
        $info = Db::name('user_sign')->alias('us')->field($field)
                        ->join($join)->where($map)->find();

        ($info['lastsign'] != date("Y-n-j", time())) && $tab = "1";

        $signtime = explode(",", $info['signtime']);
        $str = "";
        //是否标识历史签到
        if (date("m", strtotime($info['lastsign'])) == date("m", time())) {
            foreach ($signtime as $val) {
                $str .= date("j", strtotime($val)) . ',';
            }
            $this->assign('info', $info);
            $this->assign('str', $str);
        }

        $this->assign('cumtrapz', $info['cumtrapz']);
        $this->assign("jifen", ($config['sign_signcount'] * $config['sign_integral']) + $config['sign_award']);
        $this->assign('config', $config);
        $this->assign('tab', $tab);

        return $this->fetch();
    }

    public function accountSafe()
    {
        return $this->fetch();
    }

    /**
     * 用户分享列表
     */
    public function zpshare_list()
    {
        $user_id = $this->user_id;
        // dump($user_id);die;
        $user = M('share')->where(['user_id'=>$user_id])->select();
        // dump($user);die;
        $_user = array();
        foreach ($user as $key => $val) {

            $_u = $val;
            $_u['share_t'] = $val['share_t'] != 0 ? date('Y-m', $val['share_t']) : '0000-00-00';
            $_user[] = $_u;
        }

        // $_month_list_c = [];
        // foreach ($_user as $key => $val) {
        //     $_t = $val;
        //     $_month_list_c[$val['share_t']][] = $_t;
        // }

        // $_conut_month_c = [];
        //  $count = 0;
        // foreach ($_month_list_c as $key => $val) {
        //     $_m['count'] = totalpost_money($val);
        //     $_m['list'] = $val;
        //     $_conut_month_c[$key] = $_m;
        // }
        // print_r($_conut_month_c);

        $this->assign('user', $_user);
        return $this->fetch();
    }

    /*
     *清空分享
     */
    public function share_empty(){
        $user_id = $this->user_id;
        $res = M('share')->where(['user_id' => $user_id])->delete();
        if ($res) {
            $data[] =  $data;
                        $return = [];
                        $return['status'] = 'success';
                        $return['message'] = '清空分享成功';
                        $return['data'] = $data;
                        echo json_encode($return);
                        exit;
        } else {
            $data[] =  $data;
                $return = [];
                $return['status'] = 'error';
                $return['error'] = '清空分享失败';
                $return['data'] = [];
                echo json_encode($return);
                exit;
        }
    }

    /**
     * 用户评价列表
     */
    public function zpevaluate_list()
    {
        $user_id = $this->user_id;
        $user = M('comment')->where(['user_id'=>$user_id])->select();
        $_user = array();
        foreach ($user as $key => $val) {

            $_u = $val;
            $_u['head_pic'] = M('users')->where(['user_id'=>$val['user_id']])->value('head_pic');
            $_u['nickname'] = M('users')->where(['user_id'=>$val['user_id']])->value('nickname');
            $rank = ($val['deliver_rank'] + $val['goods_rank'] + $val['service_rank']) / 3;
            $_u['rank'] = round($rank,0);
            $_u['img'] = unserialize($val['img']); // 晒单图片
            $_u['add_time'] = $val['add_time'] != 0 ? date('Y-m-d H:i:s', $val['add_time']) : '0000-00-00 00:00:00';
            $_u['star_images'] = star($_u['rank']);
            $_u['original_img'] = M('goods')->where(['goods_id'=>$val['goods_id']])->value('original_img');
            $_u['goods_name'] = M('goods')->where(['goods_id'=>$val['goods_id']])->value('goods_name');
            $_u['shop_price'] = M('goods')->where(['goods_id'=>$val['goods_id']])->value('shop_price');
            $_u['spec_key_name'] = M('order_goods')->where(['goods_id'=>$val['goods_id']])->where(['order_id'=>$val['order_id']])->value('spec_key_name');
            $_user[] = $_u;

        }

        $this->assign('user', $_user);
        return $this->fetch();
    }

    /**
     * 用户分销列表
     */
    public function zpdistribution_list()
    {
        $user_id = $this->user_id;
        $u_id = I('u_id');
        if($u_id){
            $son['subordinate'] = M('users')->where(['user_id'=>$u_id])->find();
            $son['reg_time'] = '注册时间' . ' ' . ($son['subordinate']['reg_time'] != 0 ? date('Y.m.d', $son['subordinate']['reg_time']) : '0000.00.00');
            $son['first'] = M('users')->where(['first_leader'=>$u_id])->count('first_leader');//子用户第一层
            $son['second'] = M('users')->where(['second_leader'=>$u_id])->count('second_leader');//子用户第二层
            $son['third'] = M('users')->where(['third_leader'=>$u_id])->count('third_leader');//子用户第三层
            $son['count'] = $son['first']+$son['second']+$son['third'];//子用户第三层

            $layer = M('users')->where(['user_id'=>$u_id])->field('first_leader,second_leader,third_leader')->find();
            // $several_layers = array_search($user_id, $layer);
            if($layer['first_leader'] == $user_id){
                $son['layer'] = '第一层';
            }elseif($layer['second_leader'] == $user_id ){
                $son['layer'] = '第二层';
            }elseif($layer['third_leader'] == $user_id){
                $son['layer'] = '第三层';
            }else{
                $son['layer'] = '啊哦~出错了';
            }
            echo json_encode($son);
        }else{
        $user = M('users')->where(['user_id'=>$user_id])->find();
        $user['first_leader'] = M('users')->where(['first_leader'=>$user_id])->count('first_leader');//当前用户第一层
        $user['second_leader'] = M('users')->where(['second_leader'=>$user_id])->count('second_leader');//当前用户第二层
        $user['third_leader'] = M('users')->where(['third_leader'=>$user_id])->count('third_leader');//当前用户第三层
        $user['count_leader'] = $user['first_leader'] + $user['second_leader'] + $user['third_leader'];//当前用户总
        // dump($user);die;
        $list = M('users')->whereOR(['first_leader'=>$user_id])->whereOR(['second_leader'=>$user_id])->whereOR(['third_leader'=>$user_id])->select();
        $users = '';
        // dump($list);die;
        $_list = array();
        foreach ($list as $key => $val) {
            $_l = $val;
            $users .= $val['user_id'].',';
            $_list[] = $_l;
        }

        $user_arr = array_column($list, 'user_id');

        $purchase=M('order')->where('user_id','IN',$user_arr)->where('order_status','in',['2','4'])->where('shipping_status&pay_status',1)->group('user_id')->count();//已购人数
        // echo M('order')->getlastsql();exit;

        $this->assign('purchase', $purchase);
        $this->assign('user', $user);
        $this->assign('list', $_list);
        return $this->fetch();
        }
    }

    //所有粉丝信息
    public function ajax_count_leader(){
        config('default_ajax_return', 'html');
        $type = input('type')?:'1';
        $account = input('account');
        // dump($type);
        $user_id = $this->user_id;
        $where_a =[
            'first_leader'=>$user_id,
            'second_leader'=>$user_id,
            'third_leader'=>$user_id,
        ];
        $where_b = ['first_leader'=>$user_id];
        $where_c = ['second_leader'=>$user_id];
        $where_d = ['third_leader'=>$user_id];
        // $where_d = ['user_id'=>$u_id['u_id']];
        $where_f = '';
        $where_field = '';
        if($type == 1){
            $count_leader = M('users')->whereOR($where_a)->select();
        }elseif($type ==2){
            $count_leader = M('users')->where($where_b)->select();
        }elseif($type ==3){
            $count_leader = M('users')->where($where_c)->select();
        }elseif($type ==6){
            if(!preg_match("/[^\d-., ]/",$account)){
                $count_leader =  M('users')->where(function ($query) {
                    $account = input('account');
                    $query->where('user_id',$account);
                    })->where(function ($query) {
                        $user_id = $this->user_id;
                        $query->whereOR('first_leader',$user_id)->whereOR('second_leader',$user_id)->whereOR('third_leader',$user_id);
                    })->select();

            }else{
                $count_leader =  M('users')->where(function ($query) {
                    $account = input('account');
                    $query->where('nickname','like','%'.$account.'%');
                    })->where(function ($query) {
                        $user_id = $this->user_id;
                        $query->whereOR('first_leader',$user_id)->whereOR('second_leader',$user_id)->whereOR('third_leader',$user_id);
                    })->select();

            }
        }else{
            $count_leader = M('users')->where($where_d)->select();
        }

        $_count_leader = array();
        foreach ($count_leader as $key => $val){
            $_c = $val;
            $memberorder = M('order')->where(['user_id'=>$val['user_id']])->where(['pay_status'=> 1])->field(['order_id'])->find();
            // echo M('order')->getlastsql();exit;

            if($memberorder){
                $_c['memberorder']= 1;
            } else {
                $_c['memberorder']= 0;
            }
            $_count_leader[] = $_c;
        }
            if($_count_leader){
                $this->assign('count_leader', $_count_leader);
                return $this->fetch();
        }else{
            return '';
        }
    }

    /*我的二维码*/
    public function myqrcode()
    {
        $user = $this->user;
        //加载第三方类库
        vendor('phpqrcode.phpqrcode');
        //获取个人
        $url = request()->domain().U('reg',['id'=>$this->user_id]);

        $after_path = 'public/qrcode/'.md5($url).'.png';
        //保存路径
        $path =  ROOT_PATH.$after_path;
        //判断是该文件是否存在
        if(!is_file($path))
        {
            M('users')->where("user_id", $user['user_id'])->save(array('short_url' => $url));
            //实例化
            $qr = new \QRcode();
            //1:url,3: 容错级别：L、M、Q、H,4:点的大小：1到10
            $qr::png($url,'./'.$after_path, "M", 6,TRUE);
        }
        $this->assign('qrcodeImg',request()->domain().'/'.$after_path);
        $this->assign('user',$user);
        $this->assign('url',$url);
        return $this->fetch();
    }

    /*关联上下级*/
    public function contactleader()
    {
        $is_bind_account = tpCache('demo.name');
        $parent_id = I('id/d');
        // M('users')->where('user',$parent_id)->find()
        $parent_info = Users::get($parent_id);
        if(empty($parent_info))
            $this->error('所绑定上级用户的信息有误!',U('index/index'));

        //是否已经绑定了上下级关系
        $user_info = Users::get($this->user_id);
        
        if($user_info['perpetual'])
            $this->error('您已经存在上级，不可以继续绑定',U('index/index'));

        $check = checkLeader($this->user_id, $parent_id);
        if ($check) {
            $this->redirect(U('index/index'));
        }
        // 获取后台配置
        $level_switch = Db::name('distribut_system')->where(['inc_type' => 'levels', 'name' =>'level_switch'])->value('value');
        $level_state = Db::name('distribut_system')->where(['inc_type' => 'levels', 'name' => 'level_state'])->value('value');
        
        $user_logic = new UsersLogic();
        if (!$level_switch) // 开启自动确认关系
        {

            if ($level_state == 0) {
                $res = $user_logic->bindLeader($this->user_id,$parent_id, 1);
            } else {
                $res = $user_logic->bindLeader($this->user_id,$parent_id);
            }
            $this->redirect($res['url']);
            exit;
        }
        if(IS_POST)
        {
            if ($level_state == 0) {
                $res = $user_logic->bindLeader($this->user_id,$parent_id, 1);
            } else {
                $res = $user_logic->bindLeader($this->user_id,$parent_id);
            }
            return $user_logic->bindLeader($this->user_id,$parent_id);
        }

        $this->assign('head_pic',$user_info['head_pic']);
        $this->assign('user',$parent_info);
        return $this->fetch();

    }

    /**
     * 申请工程师、门店  Lu
     */
    public function repair_join(){

        $user_id = $this->user_id;
        $step = I('step');
        //门店列表
        $suppliers = M('suppliers')->order('suppliers_id')->select();

        //维修品类
        $repair_cat = M('repair_cat')->where(['level'=>2,'is_show'=>1])->order('parent_id asc,sort_order asc')->select();
        $repair_cat = second_array_unique_bykey($repair_cat,'name');//去重复

        //维修故障表
        $repair_fault= M('repair_fault')->where(['is_show'=>1])->order('sort_order asc')->select();
        $repair_fault = second_array_unique_bykey($repair_fault,'name');
        $repair_join = M('repair_join')->where(['user_id'=>$user_id,'type'=>1])->find();

        if($repair_join){
            $repair_joininfo = M('repair_joininfo')->where(['join_id'=>$repair_join['join_id']])->find();
        }


        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $repair_joininfo['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $repair_joininfo['city'], 'level' => 3))->select();
        if ($repair_joininfo['twon']) {
            $e = M('region')->where(array('parent_id' => $repair_joininfo['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }

        $repair_joininfo['where_know'] = unserialize($repair_joininfo['where_know']);
        $repair_joininfo['repair_cat'] = explode(',',$repair_joininfo['repair_cat'] );
        $repair_joininfo['repair_fault'] = explode(',',$repair_joininfo['repair_fault'] );
   
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('repair_join',$repair_join);
        $this->assign('repair_joininfo',$repair_joininfo);
        $this->assign('suppliers',$suppliers);
        $this->assign('repair_cat',$repair_cat);
        $this->assign('repair_fault',$repair_fault);

        if($step == 3)return $this->fetch('repair_joinstate');
        if($step == 2)return $this->fetch('repair_joininfo');
        return $this->fetch('repair_join');
    }

    /**
     * 申请提交  Lu
     */
    public function add_repair_join(){
        $step = I('step');
        $user_id = $this->user_id;

        if (IS_POST) {

            $post_data = input('post.');

            //加盟状态判断
            if(!$step){
                $repair_join = M('repair_join')->field('join_id,status')->where(['user_id'=>$user_id,'type'=>1])->find();
                if($repair_join['status'] === 0 && $repair_join['is_show'] === 1) return array('status'=>-11,'msg'=>'您的加盟信息正在审核中');
                if($repair_join['status'] == 1) return array('status'=>-11,'msg'=>'您已经通过加盟');
                // 检索当前手机号是否存在门店表
                $res = Db::name('suppliers')->where(['account'=>$post_data['mobile']])->find();
                if ($res) return array('status'=>-11,'msg'=>'该账号已经加盟');
                $post_data['type'] = 1;
            }

            if(array_key_exists('where_know',$post_data))$post_data['where_know'] = serialize($post_data['where_know']);
            $id = !$post_data['id'] && $repair_join['join_id'] ? $repair_join['join_id'] : $post_data['id'];//防止产生多条数据
            $type = $post_data['type'];
            if($step == 3) $post_data['status'] = 0;  //更新审核状态
            $logic = new UsersLogic();
            $data = $logic->add_repair_join($this->user_id, $id, $type,$post_data,$step);
            $data['url']= $step == 3 ? U('/Mobile/User/index') : U('/Mobile/User/repair_join');
            $this->ajaxReturn($data);
        }
    }



    public function test()
    {
        $a =  time() - 1523343949;
    }


    /**
     * 生成宣传海报
     * @param array  参数,包括图片和文字
     * @param string  $filename 生成海报文件名,不传此参数则不生成文件,直接输出图片
     * @return [type] [description]
     */
    function createPoster($config=array(),$filename=""){
        //如果要看报什么错，可以先注释调这个header
        if(empty($filename)) header("content-type: image/png");
        $imageDefault = array(
            'left'=>0,
            'top'=>0,
            'right'=>0,
            'bottom'=>0,
            'width'=>100,
            'height'=>100,
            'opacity'=>100
        );
        $textDefault = array(
            'text'=>'',
            'left'=>0,
            'top'=>0,
            'fontSize'=>32,       //字号
            'fontColor'=>'255,255,255', //字体颜色
            'angle'=>0,
        );
        $background = $config['background'];//海报最底层得背景
        //背景方法
        $backgroundInfo = getimagesize($background);
        $backgroundFun = 'imagecreatefrom'.image_type_to_extension($backgroundInfo[2], false);
        $background = $backgroundFun($background);
        $backgroundWidth = imagesx($background);  //背景宽度
        $backgroundHeight = imagesy($background);  //背景高度
        $imageRes = imageCreatetruecolor($backgroundWidth,$backgroundHeight);
        $color = imagecolorallocate($imageRes, 0, 0, 0);
        imagefill($imageRes, 0, 0, $color);
        // imageColorTransparent($imageRes, $color);  //颜色透明
        imagecopyresampled($imageRes,$background,0,0,0,0,imagesx($background),imagesy($background),imagesx($background),imagesy($background));
        //处理了图片
        if(!empty($config['image'])){
            foreach ($config['image'] as $key => $val) {
                $val = array_merge($imageDefault,$val);
                $info = getimagesize($val['url']);
                $function = 'imagecreatefrom'.image_type_to_extension($info[2], false);
                if($val['stream']){   //如果传的是字符串图像流
                    $info = getimagesizefromstring($val['url']);
                    $function = 'imagecreatefromstring';
                }
                $res = $function($val['url']);
                $resWidth = $info[0];
                $resHeight = $info[1];
                //建立画板 ，缩放图片至指定尺寸
                $canvas=imagecreatetruecolor($val['width'], $val['height']);
                imagefill($canvas, 0, 0, $color);
                //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
                imagecopyresampled($canvas, $res, 0, 0, 0, 0, $val['width'], $val['height'],$resWidth,$resHeight);
                $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']) - $val['width']:$val['left'];
                $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']) - $val['height']:$val['top'];
                //放置图像
                imagecopymerge($imageRes,$canvas, $val['left'],$val['top'],$val['right'],$val['bottom'],$val['width'],$val['height'],$val['opacity']);//左，上，右，下，宽度，高度，透明度
            }
        }
        //处理文字
        if(!empty($config['text'])){
            foreach ($config['text'] as $key => $val) {
                $val = array_merge($textDefault,$val);
                list($R,$G,$B) = explode(',', $val['fontColor']);
                $fontColor = imagecolorallocate($imageRes, $R, $G, $B);
                $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']):$val['left'];
                $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']):$val['top'];
                imagettftext($imageRes,$val['fontSize'],$val['angle'],$val['left'],$val['top'],$fontColor,$val['fontPath'],$val['text']);
            }
        }
        //生成图片
        if(!empty($filename)){
            $res = imagejpeg ($imageRes,$filename,90); //保存到本地
            imagedestroy($imageRes);
            if(!$res) return false;
            return $filename;
        }else{
            imagejpeg ($imageRes);     //在浏览器上显示
            imagedestroy($imageRes);
        }
    }

    /*
     * 配额明细
     * 1：收入；2：支出 8：购买商品 9：购物送配额 10 : 回收出售商品
     */
    public function frozen_list()
    {
        $page = (int)I('p') ? (int)I('p') : 0;
        $list = 15;
        $logs = Db::name('integrallog')
                        ->where(['userId' => $this->user_id])
                        ->order('id desc')
                        ->limit($page*$list, $list)
                        ->select();
        foreach ($logs as $k=>$v) {
            $logs[$k]['createTime'] = date('Y-m-d',strtotime($v['createTime']));
            switch ($v['type']) {
                case 1 :
                    $logs[$k]['type_str'] = "收入";
                    break;
                case 2 :
                    $logs[$k]['type_str'] = "支出";
                    break;
                case 8 :
                    $logs[$k]['type_str'] = "购买商品";
                    break;
                case 9 :
                    $logs[$k]['type_str'] = "购物送配额";
                    break;
                case 10 :
                    $logs[$k]['type_str'] = "回收出售商品";
                    break;
                default:
                    $logs[$k]['type_str'] = "收入";
                    break;
            }
        }
        if(IS_POST){
            $this->ajaxReturn($logs);
        }
        return $this->fetch();
    }
    
    /**
     * 帮助中心
     */
    public function help_center()
    {
        $where = array(
            'ac.cat_id' => 15
        );
        $ArticleList = M('article_cat')
            ->alias('ac')
            ->join('__ARTICLE__ a','ac.cat_id=a.cat_id','RIGHT')
            ->where($where)
            ->select();

        $this->assign('ArticleList',$ArticleList);
        return $this->fetch();
    }

    public function my_poster(){
        $user = $this->user;

        //加载第三方类库
        vendor('phpqrcode.phpqrcode');
        //获取个人
        $url = request()->domain().U('contactleader',['id'=>$this->user_id]);
//        if(!$user['short_url']){
//            $weixin_config = M('wx_user')->find(); //获取微信配置
//            $jssdk = new JssdkLogic($weixin_config['appid'], $weixin_config['appsecret']);
//            $data = $jssdk->getshorturl($url);
//            M('users')->where("user_id", $user['user_id'])->save(array('short_url' => $data['short_url']));
//            $short_url = $data['short_url'];
//        }else{
//            $short_url = $user['short_url'];
//        }

        $after_path = 'public/qrcode/'.md5($url).'.png';
        //保存路径
        $path =  ROOT_PATH.$after_path;

        //判断是该文件是否存在
        if(!is_file($path))
        {
            //实例化
            $qr = new \QRcode();
            //1:url,3: 容错级别：L、M、Q、H,4:点的大小：1到10
            $qr::png($url,'./'.$after_path, "M", 6,TRUE);
        }
        $poster = Db::name('poster')->where(['type' => 1])->find();

        // 二维码设置不为空
        if ($poster['qr_width']){
            $config['image'][] = [
                'url'=>$path,     //二维码资源
                'left'=>$poster['qr_x'],
                'top'=>$poster['qr_y'],
                'stream'=>0,             //图片资源是否是字符串图像流
                'right'=>0,
                'bottom'=>0,
                'width'=>$poster['qr_width'],
                'height'=>$poster['qr_height'],
                'opacity'=>100
            ];
        }

        if ($poster['head_width'] ){
            $is_wx_header = strstr($user['head_pic'] ,'http');
            if ($is_wx_header) {
                // 请求微信头像回来本地
                $wx_pic_path = curl_wx_pic($user['head_pic'],$user['user_id']);
                if ($wx_pic_path){
                    Db::name('users')->where(['user_id' => $user['user_id']])->save(['head_pic' => $wx_pic_path]);
                }
                $head_pic  = 'http://'.$_SERVER['SERVER_NAME'].$wx_pic_path;
            } else {
                $head_pic  = $user['head_pic'] ? 'http://'.$_SERVER['SERVER_NAME'].$user['head_pic'] : 'http://'.$_SERVER['SERVER_NAME'].'/template/mobile/new2/static/images/user68.jpg';
            }
            $config['image'][] = [
                'url'=> $head_pic,     //二维码资源
                'left'=>$poster['head_x'],
                'top'=>$poster['head_y'],
                'stream'=>0,             //图片资源是否是字符串图像流
                'right'=>0,
                'bottom'=>0,
                'width'=>$poster['head_width'],
                'height'=>$poster['head_height'],
                'opacity'=>100
            ]   ;
        }

        if ($poster['nk_width']){
            if (!$poster['nk_font']){
                // 为空默认16
                $poster['nk_font'] = 16;
            }
            $config['text'] = [[
                'text'=>$user['nickname'],
                'left'=>$poster['nk_x'],
                'top'=>$poster['nk_y'],
                'fontPath'=>'public/ttf/simhei.ttf',     //字体文件
                'fontSize'=>$poster['nk_font'],             //字号
                'fontColor'=>$poster['nk_color'],        //字体颜色
                'angle'=>0,
            ]];
        }
//        dump($config);die;
        $config['background'] =  request()->domain(). $poster['img'] ;
        $after_path = 'public/qrcode/'.md5($user['user_id']).'.png';
//        echo $after_path;
//        die;
//        dump($config);die;

        $path =  ROOT_PATH.$after_path;
        createPoster($config,$path);

        $this->assign('qrcodeImg',request()->domain().'/'.$after_path);
        $this->assign('user',$user);
        $this->assign('url',$short_url);
        return $this->fetch();
    }

    public function app_description(){
        $data = db::name('management')->where(array('type'=>1))->find();
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $data['link'] = $data['ios_link'];
            $data['qr_code'] = $data['android_code'];
        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')) {
            $data['link'] = $data['android_link'];
            $data['qr_code'] = $data['ios_code'];
        }
        $data['details'] = htmlspecialchars_decode($data['details']);

        $this->assign('data',$data);
        return $this->fetch();
    }

    //寄售
    public function consignment()
    {
        //获取 订单id  套餐id  寄售数量
        $method = $this->request->input();
        //获取 零售价 代理商购买数量
        $goods = Db::name('order_goods')->alias('g')->join('goods_setmeal s',"s.id = {$method['goods_id']}")
            ->where("order_id = {$method['order_id']}")->field('s.goods_id,g.goods_num,s.id,s.retail_price')->select();
        if($goods['id'] == $method['goods_id']){
            if($method['num'] >$goods['goods_num']){
                $this->error('寄售数量超出拥有数量!');
            }
        }
        if(Db::name('goods_consignment')->add($method)){
            $this->success('寄售成功');
        }else{
            $this->success('寄售失败');
        }
    }
//    提货
    public function self_mention()
    {
        if(!$this->request->isAjax()){
            $this->error('错误页面',U('Index/index'));
        }
        //获取 订单id  套餐id  寄售数量
        $method = $this->request->input();
        //获取 零售价 代理商购买数量
        $goods = Db::name('order_goods')->alias('g')->join('goods_setmeal s',"s.id = {$method['goods_id']}")
            ->where("order_id = {$method['order_id']}")->field('s.goods_id,g.goods_num,s.id,s.retail_price')->find();
        if($goods['id'] == $method['goods_id']){
            if($method['num'] >$goods['goods_num']){
                $this->error('寄售数量超出拥有数量!');
            }
        }
        $orderLogic = new OrderLogic();
        $data = [];
        //订单编号
        $data['self_mention_sn'] = $orderLogic->get_order_sn();
        $data['order_id'] = $method['order_id'];
        $data['setmeal_id'] = $method['goods_id'];
        $data['num'] = $method['num'];
        $data['status'] = 1;
        $data['create_time'] = date('Y-m-d H:i:s');
        if(Db::name('self_mention_order')->add($data)){
            $this->success('提货成功！');
        }else{
            $this->success('提货失败！');
        }
    }
    //收益汇总
    public function income_summary(){
        $logs = Db::name('balancelog')->where("userId = {$this->user_id} AND type in (4,5,6,7,12)")->field("userId,type,num,DATE_FORMAT(createTime,'%Y-%m') as createTime2,sum(num) snum")->group('createTime2')->select();
        $logs2 = Db::name('shoppinglog')->where("userId = {$this->user_id} AND type in (9)")->field("userId,type,num as num,DATE_FORMAT(createTime,'%Y-%m') as createTime2,sum(num) snum")->group('createTime2')->select();
        $logs3 = Db::name('balancelog')->where("userId = {$this->user_id} AND type in (11)")->field("userId,type,id,num as num3,DATE_FORMAT(createTime,'%Y-%m') as createTime2,sum(num) snum")->group('createTime2')->select();
        $num = count($log);
        $num1 =count($logs2);
        $num2 =count($logs3);
        if($num > $num1){
            if($num >$num2){
                $j = $num;
            }else{
                $j = $num2;
            }
        }else{
            if($num1 > $num2){
                $j = $num1;
            }else{
                $j = $num2;
            }
        }
        $arr = array();
        for ($i=0; $i < $j; $i++) { 
            $arr[] = $logs[$i]['createTime2'];
            $arr[] = $logs2[$i]['createTime2'];
            $arr[] = $logs3[$i]['createTime2'];
        }
        $arr = array_unique(array_filter($arr));
        $this->assign('list',$logs);
        $this->assign('list2',$logs2);
        $this->assign('list3',$logs3);
        $this->assign('arr',$arr);
        return $this->fetch();
    }
    //收益查询 1：充值；2：申请提现；3：提现失败返还 4：直推下级业绩返点 5：代理商补贴  6 : 直推代理商收入 7：月销售额收入；8：收益积分购买产品；9：转出；10：转入； 11：寄售商品收入 12：合伙人补贴；13：转让手续费
    public function income_inquiry(){
        $page = (int)I('p') ? (int)I('p') : 0;
        $list = 15;
        $where['userId']= $this->user_id;
        $where['type']= ['in','4,5,6,7,11,12'];
        if(I('time1')&&I('time2')){
            $where['createTime']=['between time',[I('time1'),I('time2').'23:59:59']];
        }
        $logs = Db::name('balancelog')->where($where)->order('id desc')->limit($page*$list, $list)->select();
        foreach ($logs as $k=>$v) {
            $logs[$k]['createTime'] = date('Y-m-d',strtotime($v['createTime']));
            $logs[$k]['mobile'] = $this->user['mobile'];
            switch ($v['type']) {
                case 4 :
                    $logs[$k]['type_str'] = "直推下级业绩返点";
                    break;
                case 5 :
                    $logs[$k]['type_str'] = "代理商补贴";
                    break;
                case 6 :
                    $logs[$k]['type_str'] = "直推代理商收入";
                    break;
                case 7 :
                    $logs[$k]['type_str'] = "月销售额收入";
                    break;
                case 11 :
                    $logs[$k]['type_str'] = "寄售商品收入";
                    break;
                case 12 :
                    $logs[$k]['type_str'] = "合伙人补贴";
                    break;
                default:
                    $logs[$k]['type_str'] = "收入";
                    break;
            }
        }
        if(IS_POST){
            $this->ajaxReturn($logs);
        }
        return $this->fetch();
    }
    //商品明细
    public function product_breakdown(){
        return $this->fetch();
    }
    //我的粉丝
    public function my_fans(){
        $where['first_leader']= $this->user_id;
        $logs = Db::name('users')->where($where)->order('user_id desc')->select();
        foreach ($logs as $key => $value) {
            $logs[$key]['mobile']=substr_replace($value['mobile'],'****',3,4);
        }
        $this->assign('list',$logs);
        $this->assign('count',count($logs));
        $this->assign('user_id',$this->user_id);
        return $this->fetch();
    }
    //7天默认收货  //每日触发
    public function confirm_order()
    {
    // 发货后满多少天自动收货确认
        $auto_confirm_date = tpCache('shopping.auto_confirm_date');
        $auto_confirm_date = $auto_confirm_date * (60 * 60 * 24); // 7天的时间戳
        $time = time() - $auto_confirm_date; // 比如7天以前的可用自动确认收货
        $order_id_arr = M('order')->where("order_status = 1 and shipping_status = 1 and shipping_time < $time")->getField('order_id',true);
        foreach($order_id_arr as $k => $v)
        {
            confirm_order($v);
        }
    }
}
