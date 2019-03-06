<?php

namespace app\dcworld\controller;

use think\Db;
use think\AjaxPage;


class Dcworld extends Sign
{
    public $user = array();
    public $user_id = 0;

    public function _initialize()
    {
        parent::_initialize();
        $user = $this->getAccount();

        $trade_action = array('trading_center', 'buy', 'buy_in', 'buy_dc', 'sell_dc', 'immediately_sell', 'sell_out', 'confirm_the_money');
        if (in_array(ACTION_NAME, $trade_action)) {
            //取消过期交易订单
            $WorldLogic = new \app\common\logic\WorldLogic();
            $WorldLogic->cancel_trade_order();
        }

        $user_info = M('member')->where(array('id' => $user['id'],'isDelete'=>2))->find();
        if(empty($user_info)){
            $this->redirect(U('Login/exitLogin'));
        }

        $level = db('world_level')->where(array('level_id' => $user_info['level']))->find();
        $user_info['level_name'] = $level['level_name'];
        $this->user = $user_info;
        $this->user_id = $user['id'];
    }

    /**
     * 个人中心 index
     */
    public function index()
    {
        $uid = $this->user_id;
        $uinfo = M('member')
            ->alias("t")
            ->field("t.*,b.*")
            ->join("world_level b", "t.level=b.level_id", 'LEFT')
            ->where(array("id" => $uid))
            ->find();
        $article = Db::name('article')->where("article_id", 40)->find();
        $this->assign('article', $article);
        $this->assign('uinfo', $uinfo);
        $this->assign('user', $this->user);
        $system = tpCache("vpay_spstem");
        $this->assign('appVersion', $system['appVersion']);
        return $this->fetch();
    }

    /**
     * 沙滩
     */
    public function sandy_beach()
    {
        $uid = $this->getAccountId();
        $uinfo = M('member')
            ->alias("t")
            ->field("t.*,b.*")
            ->join("world_level b", "t.level=b.level_id", 'LEFT')
            ->where(array("id" => $uid))
            ->find();
        $article = Db::name('article')->where("article_id", 39)->find();
        $this->assign('uinfo', $uinfo);
//        dump($uinfo);die;

        $shell_data = create_shell($uid);

        $this->assign('user', $this->user);
        $this->assign('article', $article);
        $this->assign('shell_data', $shell_data);
        return $this->fetch();
    }

    /**
     * 捡贝壳
     */
    public function get_shell()
    {
        $uid = $this->getAccountId();
        $pdata = input('post.');

        $get_shell = M('world_getshell')->where(array('id' => $pdata['id']))->find();

        $shell_info = unserialize($get_shell['shell_info']);

        if($shell_info[$pdata['shell_index']]['status'] == 1){
            $this->ajaxError('已捡贝壳');
        }

        $shell_info[$pdata['shell_index']]['status'] = 1;
        $number = $shell_info[$pdata['shell_index']]['num'];


        $res = M('world_getshell')->where(array('id' => $pdata['id']))->update(array('shell_info' => serialize($shell_info)));
        $res1 = M('world_giveshell')->where(array('id' => $get_shell['giveshell_id']))->setDec('shell_surplus', $number);
        $res2 = accountShellLog($uid, $number, '捡贝壳', $get_shell['id'], '', 2, 1);

        if (!$res) {
            $this->ajaxError('捡贝壳失败');
        }
        $shell_num = Db::name('member')->where(array('id' => $uid))->getField('shell');
        $this->ajaxSuccess($shell_num);
    }

    /**
     * 收取dc-油滴
     */
    public function get_dc_oil()
    {
        $uid = $this->getAccountId();
        $pdata = input('post.');

        $world_release = M('world_release')->where(array('id' => $pdata['id']))->find();

        $oil_info = unserialize($world_release['oil_info']);
        if($oil_info[$pdata['oil_index']]['status']==1){
            $this->ajaxError('已收取');
        }

        $oil_info[$pdata['oil_index']]['status'] = 1;
        $number = $oil_info[$pdata['oil_index']]['num'];

        $res = M('world_release')->where(array('id' => $pdata['id']))->update(array('oil_info' => serialize($oil_info)));
        $res1 = M('world_release')->where(array('id' => $world_release['id']))->setDec('surplus_dc', $number);
        $res2 = accountDcLog($uid, $number, '矿机产出', $world_release['id'], '', 9, 1);

        if (!$res) {
            $this->ajaxError('收取DC失败');
        }
        $dc_coin_num = Db::name('member')->where(array('id' => $uid))->getField('dc_coin');
        $this->ajaxSuccess($dc_coin_num);
    }


    /**
     * 兑换DC
     */
    public function exchange_dc()
    {
        //信息
        $user = session('account');
        $user_info = M('member')->where(array('id' => $user['id']))->find();//实时数据

        //兑换比例
        $proportion = worldCa("basic.dc_shell_rate");
        $dc_cny_rate = worldCa("basic.dc_cny_rate");
        if (IS_POST) {
            $data = I("post.");
            if ($user_info['shell'] < $data['shell_number']) $this->ajaxError('贝壳数量不足');
            if(!$proportion||!$dc_cny_rate){
                $this->ajaxError('系统参数错误');
            }

            $dc_num = $data['shell_number']/$proportion;

            //贝壳兑换表明细
            $ShellExchangeDate = array(
                'user_id' => $user_info['id'],
                'number' => $data['shell_number'],
                'dc_shell_rate' => $proportion,
                'dc_cny_rate' => $dc_cny_rate,
                'status' => 1,
                'create_time' => time()
            );
            $shell_exchange = M('world_shell_exchange')->add($ShellExchangeDate);
                
            //贝壳流水表明细
            if ($shell_exchange) {
                $WorldShelLog = array(
                    'user_id' => $user_info['id'],
                    'number' => '-' . $data['shell_number'],
                    'before' => $user_info['shell'],
                    'after' => ($user_info['shell'] - $data['shell_number']),
                    'change_time' => time(),
                    'desc' => '贝壳兑换DC',
                    'order_id' => $shell_exchange,
                    'type' => 3
                );
                $world_shell_exchange = M('world_shell_log')->add($WorldShelLog);

                //DC币明细表
                $WorldDcLog = array(
                    'user_id' => $user_info['id'],
                    'number' => $dc_num,
                    'before' => $user_info['dc_coin'],
                    'after' => ($user_info['dc_coin'] + $dc_num),
                    'change_time' => time(),
                    'desc' => '贝壳兑换DC',
                    'order_id' => $shell_exchange,
                    'type' => 10
                );
                $world_dc_log = M('world_dc_log')->add($WorldDcLog);
            }

            if ($world_shell_exchange && $world_dc_log) {
                $where = array(
                    'dc_coin' => array('exp', 'dc_coin+' . $dc_num),
                    'shell' => array('exp', 'shell-' . $data['shell_number'])
                );
                $result = M('member')->where(array('id' => $user_info['id']))->update($where);
                if ($result) $this->ajaxSuccess('兑换成功');
            }
            $this->ajaxError('兑换失败');
        }

        $this->assign('user', $user_info);
        $this->assign('proportion', $proportion);
        return $this->fetch();
    }

    /**
     * 贝壳记录
     */
    public function shell_log()
    {
        $is_ajax = I('is_ajax');
        if ($is_ajax == 1) {
            //信息
            $user_info = session('account');
            $where = array();
            $where['user_id'] = $user_info['id'];

            //分页数据
            $count = M('world_shell_log')->where($where)->count();
            $Page = new AjaxPage($count, 10);
            $data = db('world_shell_log')->where($where)->limit($Page->firstRow, $Page->listRows)->order('change_time desc')->select();

            $dc_shell_rate = worldCa('basic.dc_shell_rate');
            $dc_cny_rate = worldCa('basic.dc_cny_rate');

            foreach ($data as $k => $v) {
                $DcNumber = $v['number'] / $dc_shell_rate;
                $DcNumber=floor($DcNumber*1000)/1000;
                $data[$k]['dc_number'] = sprintf("%.3f",$DcNumber);
                $cny = $DcNumber * $dc_cny_rate;
                $cny=floor($cny*1000)/1000;
                $data[$k]['cny'] = sprintf("%.3f",$cny);
            }

            $this->assign('data', $data);
            return $this->fetch('ajax_shell_log');
        }
        return $this->fetch();
    }

    /**
     * 矿场
     */
    public function mine_field()
    {
        set_time_limit(0);
        ini_set ('memory_limit', '512M');
        $user_info = $this->user;
        $user_info['income_surplus'] = M('world_users_miner')
            ->where(array('user_id' => $user_info['id'], 'status' => 1))
            ->sum('income_surplus');

        $oilList = create_oil($user_info['id']);

        $this->assign('user', $user_info);
        $this->assign('oilList', $oilList);
        return $this->fetch();
    }

    /**
     * 我的矿机
     */
    public function my_miner()
    {
        set_time_limit(0);
        ini_set ('memory_limit', '512M');
        $user_info = $this->user;

        $data = I('get.');

        //获取矿机统计
        $count = M('world_users_miner')
            ->alias('um')
            ->field('um.*,count(*) as total_num,sum(income_surplus) as total_surplus')
            ->where(array('um.user_id' => $user_info['id'], 'status' => 1,'um.source_type'=>array('not in',[2,4])))
            ->find();

        //矿机机型统计
        $user_id = $user_info['id'];
        $user_miner = M('world_miner')->alias('a')
            ->join('world_users_miner um', "a.miner_id = um.miner_id  and status=1 and user_id=" . $user_id, 'LEFT')
            ->field('a.miner_id,a.miner_name,a.type,a.original_img,count(um.id) as total_num,sum(income_all-income_surplus) as total_income,sum(income_surplus) total_surplus')
            ->where(array('a.is_del' => 0))
            ->order('a.sort asc')
            ->group('a.miner_id')
            ->select();

        $notice_id = I('notice_id');
        if(isset($notice_id)&&$notice_id>0){
            sign_read($notice_id,$user_id);
        }

        $this->assign('user', $this->user);
        $this->assign('user_miner', $user_miner);
        $this->assign('count', $count);
        return $this->fetch();
    }

    /**
     * 我的矿机列表
     */
    public function my_miner_list()
    {
        set_time_limit(0);
        ini_set ('memory_limit', '512M');
        $user_info = session('account');
        $data = I('get.');
        if ($data['is_ajax'] == 1) {
            //昨日时间区间
            $yesterday_morning = strtotime(date('Y-m-d', strtotime('-1 day')));
            $today_morning = strtotime('today');

            $count = M('world_users_miner')
                ->alias('um')
                ->field('um.*,wm.miner_name')
                ->join('world_miner wm', 'um.miner_id = wm.miner_id', 'LEFT')
                ->where(array('um.user_id' => $user_info['id'], 'status' => 1, 'um.miner_id' => $data['miner_id']))
                ->count();

            $Page = new AjaxPage($count, 5);
            $user_miner = M('world_users_miner')
                ->alias('um')
                ->field('um.*,wm.miner_name,wm.original_img')
                ->join('world_miner wm', 'um.miner_id = wm.miner_id', 'LEFT')
                ->where(array('um.user_id' => $user_info['id'], 'status' => 1, 'um.miner_id' => $data['miner_id']))
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->order('order_id desc,id')
                ->select();

            foreach ($user_miner as $k => $v) {
                $user_miner[$k]['time_remaining'] = date("Y-m-d", strtotime("+" . ($v['scrap_days'] - $v['release_days']) . "day"));
                $earnings_where = array(
                    'order_id' => $v['id'],
                    'type' => array('in', array(5, 6)),
                    'change_time' => array('between', array($yesterday_morning, $today_morning))
                );
                //昨日收益
                $user_miner[$k]['earnings'] = M('world_power_log')->where($earnings_where)->sum('number');
                $user_miner[$k]['earnings'] =$user_miner[$k]['earnings'] <0?-$user_miner[$k]['earnings']:$user_miner[$k]['earnings'];
//                    dump($user_miner);

            }

            $this->assign('user_miner', $user_miner);
            return $this->fetch('ajax_my_miner');
        }

        //当前矿机
        $miner = M('world_miner')->where(array('miner_id' => $data['miner_id']))->find();
        //获取矿机机型统计
        $user_miner = M('world_users_miner')
            ->field('count(*) total_num,sum(income_surplus) as total_surplus')
            ->where(array('user_id' => $user_info['id'], 'status' => 1, 'miner_id' => $data['miner_id']))
            ->find();
        $this->assign('user', $this->user);
        $this->assign('miner', $miner);
        $this->assign('user_miner', $user_miner);
        return $this->fetch();
    }


    /**
     * 我的矿池
     */
    public function my_mineral_pool()
    {
        set_time_limit(0);
        ini_set ('memory_limit', '512M');
        $user = $this->user;
        $user_info = M('member')->where(array('id' => $user['id']))->find();
        //矿机列表
        $minerList = db('world_miner')->where(array('type' => 1, 'is_on_sale' => 1, 'is_del' => 0))->order('miner_price')->field('miner_id,miner_name,short_name')->select();

        //我的直推
        $first_leader_list = db('member')->alias('a')
            ->where(array('a.parentId' => $user['id']))
            ->order('id')
            ->field('id,nickname,profilePhoto,account')
            ->select();
        foreach ($first_leader_list as &$value) {
            $value['account'] = substr_replace($value['account'], '****', 3, 4);
            $value['miner_list'] = db('world_miner')->alias('a')
                ->join('world_users_miner um', 'a.miner_id=um.miner_id and um.status=1 and um.user_id=' . $value['id'], 'left')
                ->where(array('type' => 1, 'is_on_sale' => 1, 'is_del' => 0))
                ->order('a.miner_price')
                ->field('a.miner_id,miner_name,short_name,count(um.id) as num')
                ->group('a.miner_id')
                ->select();
        }
//        dump($first_leader_list);

        foreach ($minerList as &$value) {
            $first_leader_miner = db('member')->alias('a')
                ->join('world_users_miner um', 'a.id=um.user_id')
                ->where(array('a.parentId' => $user['id'], 'um.miner_id' => $value['miner_id'], 'um.status' => 1))
                ->count();
            $value['first_leader_miner'] = $first_leader_miner;
        }

        //下级矿机总台数
        $count = array();
        $first_leader_miner = db('member')->alias('a')
            ->join('world_users_miner um', 'a.id=um.user_id')
            ->where(array('a.parentId' => $user['id'], 'um.status' => 1,'um.source_type'=>array('not in',[2,4])))
            ->count();
        $count['first_leader_miner'] = $first_leader_miner;

        //矿机统计
        $level_up_condition = M('world_level')->where(array('level_id' => ($user_info['level'] + 1)))->find();
        $up_condition = unserialize($level_up_condition['up_condition']);//规则
        $WorldLogic = new \app\common\logic\WorldLogic();
        $statistics = $WorldLogic->statisticsLowerMiners($up_condition, $user);
        $types_statistical = $WorldLogic->typeMinerStatistics($user);

        //数量百分比
        if ($statistics['cumulative_miner']) {
            $miner_scale = round($statistics['Need_miner'] / $statistics['cumulative_miner'] * 100);
        } else {
            $miner_scale = 100;
        }


        //名称数组
        $miner_list_data = array();
        foreach ($minerList as $k => $v) {
            $miner_list_data[$v['miner_id']] = $v;
        }

        $this->assign('types_statistical', $types_statistical);
        $this->assign('miner_scale', $miner_scale);
        $this->assign('miner_list_data', $miner_list_data);
        $this->assign('up_condition', $up_condition);
        $this->assign('statistics', $statistics);
        $this->assign('user', $user);
        $this->assign('minerList', $minerList);
        $this->assign('first_leader_list', $first_leader_list);
        $this->assign('count', $count);
        return $this->fetch();
    }

    /**
     * 矿机交易
     */
    public function miner_transaction()
    {
        //用户信息
        $user = session('account');
        $user_info = M('member')
            ->alias('m')
            ->field('m.*,wl.level_name,wl.buy_rights')
            ->join('world_level wl', 'm.level = wl.level_id', 'LEFT')
            ->where(array('m.id' => $user['id']))
            ->find();


        //矿机列表
        $where = array(
            'type' => 1,
            'is_on_sale' => 1,
            'is_del' => 0
        );
        $miner_list = M('world_miner')->where($where)->order('sort asc')->select();


        $ManagedForce = M('world_users_miner')->where(array('user_id' => $user_info['id'], 'status' => 1))->sum('income_surplus');
        $miner_count = M('world_users_miner')->where(array('user_id' => $user_info['id'], 'status' => 1,'source_type'=>array('in',array(1,3))))->count();


        $this->assign('qualification', explode(',', $user_info['buy_rights']));
        $this->assign('user', $this->user);
        $this->assign('ManagedForce', $ManagedForce);
        $this->assign('miner_count', $miner_count);
        $this->assign('miner_list', $miner_list);

        return $this->fetch();
    }

    /**
     * DC转账
     */
    public function transfer_accounts_dc()
    {
        $user_id = $this->user_id;
        $user = Db::name('member')->where(array('id' => $user_id))->find();
        $fee = worldCa('basic.transfer_fee');
        if (IS_POST) {
            $data = input('post.');
            $data['wallet'] = trim($data['wallet']);


            if (!$data['wallet']) {
                exit(json_encode(['code' => -1, 'msg' => '请输入转出钱包']));
            }
            if ($user['dc_coin'] < $data['number']){
                exit(json_encode(['code' => -1, 'msg' => 'DC币余额不足']));
            }
            if (!$data['number']) {
                exit(json_encode(['code' => -1, 'msg' => '请输入转出数量']));
            }
            $target = M('member')->where(['wallet' => $data['wallet']])->find();
            if($user['level'] == 1){
                exit(json_encode(['code' => -1, 'msg' => '游客身份不能进行转账']));
            }
            if($target['level'] == 1 ){
                exit(json_encode(['code' => -1, 'msg' => '对方用户为游客身份不能转账']));
            }
            if (!$target) {
                exit(json_encode(['code' => -1, 'msg' => '转出用户不存在']));
            }
            if ($target['id'] == $this->user_id) {
                exit(json_encode(['code' => -1, 'msg' => '不可转账给自己']));
            }

            if(md5($data['pwd'])!=$user['paypassword']){
                $result = array(
                    'code' => -2,
                    'msg' => '支付密码不正确',
                    'data' => ""
                );
                exit(json_encode($result));
            }


            // $fee = Db::name('world_system')->where(['inc_type' => 'basic', 'name' => 'transfer_fee'])->value('value');
            $number = $data['number'];
            if ($fee) {
                // 计算手续费
                $cut_fee = $data['number'] * $fee / 100;
                $number = $data['number'] - $cut_fee;
            }
            $desc = $data['desc'];
            if (!$data['desc']) {
                $desc = '转赠失去';
                $data['desc'] = '转赠获得';
            }
            $user_transfer_data = array(
                'user_id' => $this->user_id,
                'account' => $this->user['account'],
                'to_user_id' => $target['id'],
                'money' => $number,
                'to_account' => $target['account'],
                'create_time' => time(),
                'dc_transfer_rate' => $fee,
                'transfer_fee' => $cut_fee,
                'type' => 1,
                'remark' => $data['desc'],
            );
            $user_transfer_data = array(
                'user_id' => $this->user_id,
                'account' => $this->user['account'],
                'to_user_id' => $target['id'],
                'money' => $number,
                'to_account' => $target['account'],
                'create_time' => time(),
                'dc_transfer_rate' => $fee,
                'transfer_fee' => $cut_fee,
                'type' => 1,
                'remark' => $data['desc'],
            );
            Db::startTrans();
            $trans_res = M('world_dc_transfer')->add($user_transfer_data);
            $target_res = accountDcLog($target['id'], $number, "他人转入", $trans_res, '', 11, 1);
            $user_res = accountDcLog($this->user_id, -$data['number'], "转给他人", $trans_res, '', 12, 1);
            message_notificat($target['id'],3,'DC币转入','收到'.$number.'DC');
            if (!$trans_res && !$target_res && !$user_res) {
                Db::rollback();
                exit(json_encode(['code' => -1, 'msg' => '交易失败，请稍候尝试']));
            } else {

                Db::commit();
                exit(json_encode(['code' => 1, 'msg' => '转账成功', 'url' => url('Member/my_wallet')]));
            }
            die;
        }
        $act = I('act');
        if ($act) {
            $wallet = I('wallet');
            $to_user = M('member')->where(['wallet' => $wallet])->find();
        }
        $user = M('member')->where(['id' => $this->user_id])->find();
        $this->assign('user', $user);
        $this->assign('act', $act);
        $this->assign('to_user', $to_user);
        $this->assign('fee', $fee);
        return $this->fetch();
    }

    /*
     * 获取Dc币手续费
     * */
    public function getDcFee()
    {
        $fee = Db::name('world_system')->where(['inc_type' => 'basic', 'name' => 'transfer_fee'])->value('value');
        if ($fee) {
            // 计算手续费
            $fee = input('post.number') * $fee / 100;
        } else {
            $fee = 0;
        }
        exit(json_encode(['code' => 1, 'fee' => $fee]));
    }

    /**
     * 交易所
     */
    public function trading_center()
    {
        $user_id = $this->user_id;
        //汇率
        $step = I('step') ? I('step') : 1;

        $config = worldCa('basic');
        $user = Db::name('member')->where(array('id' => $user_id))->find();
        $this->assign('config', $config);
        $this->assign('user', $user);
        $this->assign('step', $step);
        return $this->fetch();
    }


    /**
     * 交易列表
     */
    public function trade_list()
    {
        $user_id = $this->user_id;
        $is_ajax = I('is_ajax');
        if ($is_ajax == 1) {
            $pdata = input('post.');
            //信息
            $user_info = session('account');
            $where = array();
            $type = $pdata['type'];
            switch ($type) {
                case 1:
                    $where['status'] = 1;
                    $where['type'] = 2;
                    break;
                case 2:
                    $where['status'] = 1;
                    $where['type'] = 1;
                    break;
                case 3:
                    $where['seller_user_id|buyer_user_id'] = $user_id;
                    $where['status'] = array('in', [1]);
                    $where['pid'] = array('eq', 0);
                    break;
                case 4:
//                    $where['seller_user_id|buyer_user_id'] = $user_id;
//                    $where['status'] = array('in', [2, 3]);
//                    $where['pid'] = array('gt', 0);
                    $where="( `seller_user_id` = ".$user_id." OR `buyer_user_id` =".$user_id." )  AND (case when pid=0 then  `status`=1 when pid>0 then `status` in (2,3) end)";
                    break;
                case 5:
//                    $where['seller_user_id|buyer_user_id'] = $user_id;
//                    $where['status'] = array('in', [-1, 4]);
                    $where="( `seller_user_id` = ".$user_id." OR `buyer_user_id` =".$user_id." )  AND (case when pid=0 then  `status`=-1 when pid>0 then `status`=4 end)";
                    break;
            }
//            dump($where);
            //分页数据
            $count = M('world_dc_trade')->where($where)->count();
            $Page = new AjaxPage($count, 10);
            $data = Db::name('world_dc_trade')->alias('a')
                ->join('member m1', 'a.buyer_user_id=m1.id', 'left')
                ->join('member m2', 'a.seller_user_id=m2.id', 'left')
                ->field('a.*,m1.id as bid,m1.level as blevel,m1.nickname as bnickname,m1.profilePhoto as bprofilePhoto,m1.trade_num as btrade_num,m2.id as sid,m2.level as slevel,m2.nickname as snickname,m2.profilePhoto as sprofilePhoto,m2.trade_num as strade_num')
                ->where($where)->limit($Page->firstRow, $Page->listRows)->order('id desc')->select();

//            echo Db::name('world_dc_trade')->getLastSql();


            $level_list = M('world_level')->order('level_id')->column('level_id,level_name');
            $this->assign('datalist', $data);
            $this->assign('user_id', $user_id);
            $this->assign('level_list', $level_list);
            $this->assign('type', $type);
            if (empty($data)) {
                return "";
            } else {
                return $this->fetch('ajax_trade_list');
            }

        }
        return $this->fetch();
    }

    /**
     * 检查订单数量,,是否能继续挂卖
     */
    public function check_user_orders()
    {
        $user_id = $this->user_id;
        $pdata = input('');
        $user_info = Db::name('member')->field('level')->where(['id'=>$user_id])->find();
        $config = worldCa('basic');

        if($user_info['level'] <= 2){
            $this->ajaxError('您的身份等级不允许执行该操作');
        }
        if ($pdata['type'] == 1) {
            $where = array(
                'buyer_user_id' => $user_id,
                'pid' => 0,
                'status' => 1,
                'type' => $pdata['type'],
            );
            $orders = Db::name('world_dc_trade')->where($where)->count();
            if ($config['max_buy_orders'] <= $orders) {
                $this->ajaxError('最大买入订单数为：' . $config['max_buy_orders'] . '，您当前未接单数为：' . $orders);
            }
        }
        if ($pdata['type'] == 2) {
            $where = array(
                'seller_user_id' => $user_id,
                'pid' => 0,
                'status' => 1,
                'type' => $pdata['type'],
            );
            $orders = Db::name('world_dc_trade')->where($where)->count();
            if ($config['max_sell_orders'] <= $orders) {
                $this->ajaxError('最大卖出订单数为：' . $config['max_sell_orders'] . '，您当前未接单数为：' . $orders);
            }
        }
        $this->ajaxSuccess();
    }

    /**
     * 我要买入
     */
    public function buy()
    {
        $user_id = $this->user_id;
        $pdata = input('');
        $this->assign('data', $pdata);

        $account_list = M('world_user_account')
            ->field('type as value,type_name as title')
            ->where(array('user_id' => $user_id))->order('type')->select();
        $account_one = array('value' => 0, 'title' => '请选择转账方式');
        array_unshift($account_list, $account_one);

        $this->assign('data', $pdata);
        $this->assign('account_list', json_encode($account_list));
        return $this->fetch();
    }


    /**
     * 生成买入订单
     */
    public function buy_in()
    {
        $user = $this->user;

        $pdata = input('');
        $data = $pdata;
        $order_sn = $this->get_trade_sn();
        $data['order_sn'] = $order_sn;
        $data['buyer'] = $user['account'];
        $data['buyer_user_id'] = $user['id'];
        $data['create_time'] = time();
        $data['status'] = 1;
        $data['type'] = 1;

        $config = worldCa('basic');
        $where = array(
            'buyer_user_id' => $user['id'],
            'pid' => 0,
            'status' => 1,
            'type' => 1,
        );
        $orders = Db::name('world_dc_trade')->where($where)->count();
        if ($config['max_buy_orders'] <= $orders) {
            $this->ajaxError('最大买入订单数为：' . $config['max_buy_orders'] . '，您当前未接单数为：' . $orders);
        }

        if(md5($pdata['pwd'])!==$user['paypassword']){
            $result = array(
                'code' => -2,
                'msg' => '支付密码不正确',
                'data' => ""
            );
            exit(json_encode($result));
        }


        $res = Db::name('world_dc_trade')->add($data);
        if (!$res) {
            $this->ajaxError('！下单失败，请稍后重试');
        }
        $this->ajaxSuccess();
    }


    /**
     * 买入他人的DC币
     */
    public function buy_dc()
    {
        $user = $this->user;
        if (IS_POST) {
            $pdata = input('');
            $id = $pdata['id'];
            $trade = Db::name('world_dc_trade')->where(array('id' => $id))->find();
            if ($trade['status'] != 1) {
                $this->ajaxError('！下单失败，该订单已不允许交易');
            }
            if(md5($pdata['pwd'])!==$user['paypassword']){
                $result = array(
                    'code' => -2,
                    'msg' => '支付密码不正确',
                    'data' => ""
                );
                exit(json_encode($result));
            }


            $order_sn = $this->get_trade_sn();
            $data = $trade;
            unset($data['id']);
            $data['order_sn'] = $order_sn;
            $data['pid'] = $trade['id'];
            $data['buyer'] = $user['account'];
            $data['buyer_user_id'] = $user['id'];
            $data['update_time'] = time();
            $data['status'] = 2;
            $res2 = Db::name('world_dc_trade')->add($data);
            $res1 = Db::name('world_dc_trade')->where(array('id' => $trade['id']))->update(array('status' => 2));

            if (!$res2) {
                $this->ajaxError('！下单失败，请稍后重试');
            }

            $this->ajaxSuccess(array('id' => $res2));
        } else {
            $id = I('id');
            $trade = Db::name('world_dc_trade')->where(array('id' => $id))->find();
//            dump($trade);
            $pay_type = M('world_user_account')
                ->field('type as value,account_name as title')
                ->where(array('user_id' => $trade['seller_user_id'], 'type' => $trade['pay_type']))->find();
            switch ($trade['pay_type']) {
                case 1:
                    $trade['pay_type_name'] = "支付宝";
                    break;
                case 2:
                    $trade['pay_type_name'] = "微信";
                    break;
                case 3:
                    $trade['pay_type_name'] = "银行卡";
                    break;
            }

//            dump($pay_type);
            $this->assign('data', $trade);
            $this->assign('pay_type', $pay_type);
            return $this->fetch();
        }

    }

    public function transaction_audit(){
        $user_id = $this->user_id;
        $user_info = Db::name('member')->where(['id'=>$user_id])->find();
        if($user_info['level'] <= 2){
            $this->ajaxError('您的身份无法执行该操作');
        }

        $this->ajaxSuccess();
    }

    /**
     * 卖给他人的DC币
     */
    public function sell_dc()
    {
        $user_id = $this->user_id;
        $user = Db::name('member')->where(array('id' => $user_id))->find();
        if (IS_POST) {
            $pdata = input('');
            $id = $pdata['id'];
            $trade = Db::name('world_dc_trade')->where(array('id' => $id))->find();
            if ($trade['status'] != 1) {
                $this->ajaxError('！下单失败，该订单已不允许交易');
            }

            if(md5($pdata['pwd'])!==$user['paypassword']){
                $result = array(
                    'code' => -2,
                    'msg' => '支付密码不正确',
                    'data' => ""
                );
                exit(json_encode($result));
            }

            if ($user['dc_coin'] < $pdata['number']) {
                $this->ajaxError('！数量不足您的可用DC币为' . $user['dc_coin']);
            }

            //选择的支付方式
            $pay_acocunt = M('world_user_account')
                ->field('*')
                ->where(array('user_id' => $user_id, 'type' => $pdata['pay_type']))->find();

            if (empty($pay_acocunt)) {
                $this->response(2, '！您还没有设置该转账方式，是否去设置', null);
            }

            $order_sn = $this->get_trade_sn();
            $data = $trade;
            unset($data['id']);
            $data['order_sn'] = $order_sn;
            $data['pid'] = $trade['id'];


            $data['seller'] = $user['account'];
            $data['seller_user_id'] = $user['id'];
            $data['update_time'] = time();
            $data['status'] = 2;
            $data['pay_type'] = $pay_acocunt['type'];

            $data['account_name'] = $pay_acocunt['account_name'];
            $data['account'] = $pay_acocunt['account'];
            $data['bank_name'] = $pay_acocunt['bank_name'];
            $data['bank_branch'] = $pay_acocunt['bank_branch'];
            $data['account_code'] = $pay_acocunt['account_code'];

            $res2 = Db::name('world_dc_trade')->add($data);
            $res1 = Db::name('world_dc_trade')->where(array('id' => $id))->update(array('status' => 2));
            if (!$res2) {
                $this->ajaxError('！下单失败，请稍后重试');
            }
            if ($res2) {
                //扣除DC币，增加冻结DC币
                $res1 = accountDcLog($user['id'], -$data['number'], '卖出DC', $res2, $data['order_sn'], 4, 1);
                $res2 = Db::name('member')->where(array('id' => $user['id']))->setInc('frozen_dc', $data['number']);
            }

            //消息通知
            message_notificat($trade['buyer_user_id'],1,'交易通知','卖家已接单，请尽快付款',$res2);
            $this->ajaxSuccess(array('id' => $res2));
        } else {
            $id = I('id');
            $trade = Db::name('world_dc_trade')->where(array('id' => $id))->find();

            $pay_type = M('world_user_account')
                ->field('type as value,account_name as title')
                ->where(array('user_id' => $user_id, 'type' => $trade['pay_type']))->find();

            if($pay_type){
                switch ($trade['pay_type']) {
                    case 1:
                        $trade['pay_type_name'] = "支付宝";
                        break;
                    case 2:
                        $trade['pay_type_name'] = "微信";
                        break;
                    case 3:
                        $trade['pay_type_name'] = "银行卡";
                        break;
                }
            }else{
                $trade['pay_type']=0;
            }


            $account_list = M('world_user_account')
                ->field('type as value,type_name as title')
                ->where(array('user_id' => $user_id))->order('type')->select();
            $account_one = array('value' => 0, 'title' => '请选择转账方式');
            array_unshift($account_list, $account_one);

            $this->assign('user', $user);
            $this->assign('data', $trade);
            $this->assign('account_list', json_encode($account_list));
            return $this->fetch();
        }

    }

    /**
     * 立即卖出
     */
    public function immediately_sell()
    {
        $user_id = $this->user_id;
        $pdata = input('');
        $this->assign('data', $pdata);
        $account_list = M('world_user_account')
            ->field('type as value,type_name as title')
            ->where(array('user_id' => $user_id))->order('type')->select();

        $user = Db::name('member')->where(array('id' => $user_id))->find();

        $account_one = array('value' => 0, 'title' => '请选择转账方式');
        array_unshift($account_list, $account_one);

        $this->assign('user', $user);
        $this->assign('data', $pdata);
        $this->assign('account_list', json_encode($account_list));
        return $this->fetch();
    }

    /**
     * 生成卖出订单
     */
    public function sell_out()
    {
        $user_id = $this->user_id;
        $pdata = input('');
        $user = Db::name('member')->where(array('id' => $user_id))->find();


        $config = worldCa('basic');
        $where = array(
            'seller_user_id' => $user['id'],
            'pid' => 0,
            'status' => 1,
            'type' => 2,
        );
        $orders = Db::name('world_dc_trade')->where($where)->count();
        if ($config['max_sell_orders'] <= $orders) {
            $this->ajaxError('最大卖出订单数为：' . $config['max_sell_orders'] . '，您当前未接单数为：' . $orders);
        }

        if ($user['dc_coin'] < $pdata['number']) {
            $this->ajaxError('！数量不足您的可用DC币为' . $user['dc_coin']);
        }

        if(md5($pdata['pwd'])!==$user['paypassword']){
            $result = array(
                'code' => -2,
                'msg' => '支付密码不正确',
                'data' => ""
            );
            exit(json_encode($result));
        }


        $pay_acocunt = M('world_user_account')
            ->field('*')
            ->where(array('user_id' => $user_id, 'type' => $pdata['pay_type']))->find();

        $data = $pdata;

        $data['seller'] = $user['account'];
        $data['seller_user_id'] = $user['id'];
        $data['create_time'] = time();
        $data['status'] = 1;
        $data['type'] = 2;

        $order_sn = $this->get_trade_sn();
        $data['order_sn'] = $order_sn;

        $data['account_name'] = $pay_acocunt['account_name'];
        $data['account'] = $pay_acocunt['account'];
        $data['bank_name'] = $pay_acocunt['bank_name'];
        $data['bank_branch'] = $pay_acocunt['bank_branch'];
        $data['account_code'] = $pay_acocunt['account_code'];


        $res = Db::name('world_dc_trade')->add($data);
        if (!$res) {
            $this->ajaxError('！下单失败，请稍后重试');
        }

        $res2 = Db::name('member')->where(array('id' => $user['id']))->setInc('frozen_dc', $data['number']);

        $this->ajaxSuccess();
    }

    /**
     * 确认打款（订单详情）
     */
    public function confirm_the_money()
    {
        $user_id = $this->user_id;
        $step = I('step');
        $id = I('id');
        $trade = Db::name('world_dc_trade')->alias('a')
            ->join('member m1', 'a.buyer_user_id=m1.id', 'left')
            ->join('member m2', 'a.seller_user_id=m2.id', 'left')
            ->field('a.*,m1.nickname as bnickname,m1.profilePhoto as bprofilePhoto,m1.trade_num as btrade_num,m2.nickname as snickname,m2.profilePhoto as sprofilePhoto,m2.trade_num as strade_num')
            ->where(array('a.id' => $id))->find();

        if ($trade['seller_user_id'] == $user_id) {
            switch ($trade['status']) {
                case 1:
                    $title = "";
                    break;
                case 2:
                    $title = "买家付款";
                    break;
                case 3:
                    $title = "等待放款";
                    $tips = worldCa('tips.seller_loan_tips');
                    break;
                case 4:
                    $title = "订单已完成";
                    break;
                case -1:
                    $title = "订单已取消";
                    break;
            }
        } elseif ($trade['buyer_user_id'] == $user_id) {
            switch ($trade['status']) {
                case 1:
                    $title = "";
                    break;
                case 2:
                    $title = "等待支付";
                    $tips = worldCa('tips.confirm_pay_tips');
                    break;
                case 3:
                    $title = "卖家放款";
                    break;
                case 4:
                    $title = "订单已完成";
                    break;
                case -1:
                    $title = "订单已取消";
                    break;
            }
        }


        $notice_id = I('notice_id');
        if(isset($notice_id)&&$notice_id>0){
            sign_read($notice_id,$user_id);
        }



        if ($trade['status'] == 2) {
            $cancel_time = worldCa('basic.cancel_time');
            $update_time = $trade['update_time'];
            $intDiff = ($update_time + $cancel_time * 60) - time();
            $trade['intdiff'] = $intDiff > 0 ? $intDiff : 0;
        }

        switch ($trade['pay_type']) {
            case 1:
                $trade['pay_type_name'] = "支付宝";
                break;
            case 2:
                $trade['pay_type_name'] = "微信";
                break;
            case 3:
                $trade['pay_type_name'] = "银行卡";
                break;
        }

        $this->assign('data', $trade);
        $this->assign('title', $title);
        $this->assign('tips', $tips);
        $this->assign('user_id', $user_id);

//        if ($step == 2) {
//            return $this->fetch('Waiting_the_loan');
//        } elseif ($step == 3) {
//            return $this->fetch('seller_payment');
//        }

        return $this->fetch();
    }

    /**
     *交易订单操作
     */
    public function trade_handle()
    {
        $pdata = input('post.');

        $trade = Db::name('world_dc_trade')->where(array('id' => $pdata['id']))->find();

        //买家确认打款
        if ($pdata['status'] == 3) {
            if ($trade['status'] != 2) {
                $this->ajaxError('！操作失败，该订单不允许执行此操作');
            }

            $update_data = array(
                'status' => 3,
                'update_time' => time(),
            );

            $res = Db::name('world_dc_trade')->where(array('id' => $pdata['id']))->update($update_data);
            $res = Db::name('world_dc_trade')->where(array('id' => $trade['pid']))->update(array('status' => 3));
            if (!$res) {
                $this->ajaxError('！操作失败，请稍后重试');
            }

            //消息通知
            message_notificat($trade['seller_user_id'],1,'交易通知','买家已付款，请尽快确认',$trade['id']);

            $this->ajaxSuccess();
        }

        //卖家确认打款
        if ($pdata['status'] == 4) {
            if ($trade['status'] != 3) {
                $this->ajaxError('！操作失败，该订单不允许执行此操作');
            }
            $update_data = array(
                'status' => 4,
                'update_time' => time(),
            );
            $res = Db::name('world_dc_trade')->where(array('id' => $pdata['id']))->update($update_data);

            $res1 = Db::name('member')->where(array('id' => $trade['seller_user_id']))->setDec('frozen_dc', $trade['number']);
            $res2 = Db::name('member')->where(array('id' => $trade['seller_user_id']))->setInc('trade_num', 1);
            $res3 = Db::name('member')->where(array('id' => $trade['buyer_user_id']))->setInc('trade_num', 1);

            $res1 = accountDcLog($trade['seller_user_id'], -$trade['number'], '卖出DC', $res, $trade['order_sn'], 4, 1);
            $res4 = accountDcLog($trade['buyer_user_id'], $trade['number'], '买入DC', $trade['id'], $trade['order_sn'], 7, 1);
            $res = Db::name('world_dc_trade')->where(array('id' => $trade['pid']))->update(array('status' => 4));

            if (!$res) {
                $this->ajaxError('！操作失败，请稍后重试');
            }
            //消息通知
            message_notificat($trade['buyer_user_id'],1,'交易通知','交易成功，'.$trade['number'].'DC已到账',$trade['id']);
            $this->ajaxSuccess();
        }

    }


    /**
     * 卖家取消订单
     */
    public function cancel_trade()
    {
        $user_id = $this->user_id;
        $pdata = input('post.');

        $trade = Db::name('world_dc_trade')->where(array('id' => $pdata['id']))->find();
        if ($trade['status'] > 1) {
            $this->ajaxError('！操作失败，改单已有人接单了');
        }
        if ($trade['status'] < 1) {
            $this->ajaxError('！操作失败，改单已被取消了');
        }

        $data['status'] = -1;
        $res = Db::name('world_dc_trade')->where(array('id' => $pdata['id']))->update($data);

        if (!$res) {
            $this->ajaxError('！操作失败，请稍后重试');
        }

        //卖家取消，返还DC币
        if ($trade['seller_user_id'] == $user_id) {
            $res1 = Db::name('member')->where(array('id' => $trade['seller_user_id']))->setDec('frozen_dc', $trade['number']);
//            $res2 = accountDcLog($trade['seller_user_id'], $trade['number'], 'DC交易取消', $trade['id'], $trade['order_sn'], 8, 1);
        }

        $this->ajaxSuccess();
    }


    /**
     * 订单已关闭
     */
    public function order_closed()
    {
        return $this->fetch();
    }

    /**
     * 矿机激活
     */
    public function activate_miner()
    {
        //$miner_info
        $miner_id = I('miner_id');
        $paypassword = I('paypassword');
        $user = session('account');
        $user_info = M('member')->where(array('id' => $user['id']))->find();
        $miner_info = M('world_miner')->where(array('miner_id' => $miner_id, 'is_on_sale' => 1, 'type' => 1))->find();

        if(MD5($paypassword) != $user_info['paypassword']){
            $this->ajaxError(array("error" => "支付密码错误！"));
        }
        if (!$miner_info) {
            $this->ajaxError(array("error" => "暂时没有该矿机！"));
        }
        if ($user_info['dc_coin'] < $miner_info['miner_price']) {
            $this->ajaxError(array("error" => "余额不足！"));
        }
        $order_sn = $this->get_order_sn();
        //插入订单
        $miner_order_data = array(
            'order_sn' => $order_sn,
            'user_id' => $user_info['id'],
            'type' => 1,
            'order_status' => 1,
            'pay_status' => 1,
            'goods_price' => $miner_info['miner_price'],
            'total_amount' => $miner_info['miner_price'],
            'order_amount' => $miner_info['miner_price'],
            'mobile' => $user_info['account'],
            'pay_code' => 'DC支付',
            'pay_name' => 'DC支付',
            'add_time' => time(),
            'confirm_time' => time(),
            'pay_time' => time(),
            'is_distribut' => 0,
            'deleted' => 0
        );
        Db::startTrans();
        try {
            $order_id = M('world_order')->add($miner_order_data);
            if (!$order_id) {
                Db::rollback();
                $this->ajaxError(array("error" => "订单插入失败！"));
            }

            //dc币明细表
            $world_dc_log = array(
                'user_id' => $user_info['id'],
                'order_sn' => $order_sn,
                'number' => '-' . $miner_info['miner_price'],
                'before' => $user_info['dc_coin'],
                'after' => $user_info['dc_coin'] - $miner_info['miner_price'],
                'change_time' => time(),
                'desc' => '激活矿机-'.$miner_info['short_name'],
                'order_id' => $order_id,
                'type' => 2
            );
            $dc_log_result = M('world_dc_log')->add($world_dc_log);

            //订单商品列表
            $GoodsOrderData = array(
                'order_id' => $order_id,
                'miner_id' => $miner_info['miner_id'],
                'miner_name' => $miner_info['miner_name'],
                'original_img' => $miner_info['original_img'],
                'miner_num' => 1, //购买数量暂时默认1
                'miner_price' => $miner_info['miner_price'],
                'release_size' => $miner_info['release_size'],
                'release_rate' => $miner_info['rebate_rate'],
                'scrap_days' => $miner_info['scrap_days'],
                'sort' => $miner_info['sort']
            );
            $GoodId = M('world_order_goods')->add($GoodsOrderData);

            //插入用户矿机列表
            $miner_data[] = array(
                'order_id' => $order_id,
                'user_id' => $user_info['id'],
                'miner_id' => $miner_info['miner_id'],
                'miner_price' => $miner_info['miner_price'],
                'scrap_days' => $miner_info['scrap_days'],
                'release_rate' => $miner_info['rebate_rate'],
                'income_all' => $miner_info['release_size'],
                'income_surplus' => $miner_info['release_size'],
                'add_time' => time(),
                'update_time' => time(),
                'status' => 1,
                'source_type' => 1,
                'sort' => $miner_info['sort']
            );

            //赠送矿机获得
            $presented = M('world_miner')->where(array('type' => 2))->order('sort desc')->find();
            if ($presented && $presented['is_on_sale'] == 1 && $presented['is_del'] == 0) {
                $miner_data[] = array(
                    'order_id' => $order_id,
                    'user_id' => $user_info['id'],
                    'miner_id' => $presented['miner_id'],
                    'miner_price' => $presented['miner_price'],
                    'scrap_days' => $presented['scrap_days'],
                    'release_rate' => $presented['rebate_rate'],
                    'income_all' => $miner_info['release_size_d01'],
                    'income_surplus' => $miner_info['release_size_d01'],
                    'add_time' => time(),
                    'update_time' => time(),
                    'status' => 1,
                    'source_type' => 4,
                    'sort' => $presented['sort'],
                );
            }
            $miner_result = M('world_users_miner')->insertAll($miner_data);


            //激活矿机算力
            $res=accountPowerLog($user_info['id'], $miner_info['release_size'], '激活矿机-'.$miner_info['short_name'], $order_id, $order_sn,1, 1,0);
            //激活赠送矿机算力
            $res=accountPowerLog($user_info['id'], $miner_info['release_size_d01'], '激活奖励-'.$presented['short_name'], $order_id, $order_sn,2, 1,0);

            //数据生成成功扣费
            if ($miner_result) {
                //升级系统
                $WorldLogic = new \app\common\logic\WorldLogic();
                $WorldLogic->UpgradeMethod($user_info);

                $result_one = M('member')->where(array('id' => $user_info['id']))->setDec('dc_coin', $miner_info['miner_price']);
                // if($result) $this->ajaxSuccess(array('msg' => "激活成功！"));
                if ($result_one) {
                    // 上线矿机加速释放
                    $result_two = calculation_accelerate($order_id);
                    if ($result_two) {
                        Db::commit();
                        $this->ajaxSuccess(array('msg' => "激活成功!"));
                    } else {
                        Db::rollback();
                        $this->ajaxError(array("error" => "加速释放失败!"));
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError(array("error" => "扣费失败!"));
                }

            } else {
                Db::rollback();
                $this->ajaxError(array("error" => "矿机生成失败！"));
            }
        } catch (Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 转赠
     */
    public function give_present()
    {
        //获取所需数据
        $user = session('account');
        $data = I('post.');

        $user_info = M('member')->where(array('id' => $user['id']))->find();
        // $to_user_info = M('member')->where(array('id' => $data['to_user']))->whereOr(array('account' => $data['to_user']))->find();
        $to_user_info = M('member')->where(array('id' => $data['id'], 'account' => $data['account']))->find();

        $miner_info = M('world_miner')->where(array('miner_id' => $data['miner_id']))->find();
        $world_level = M('world_level')->where(array('level_id' => $to_user_info['level']))->find();

        if(MD5($data['paypassword']) != $user_info['paypassword']){
            $this->ajaxError(array("error" => "密码输入错误！"));
        }

        if (!$to_user_info) {
            $this->ajaxError(array('error' => '该账户不存在，请重新输入'));
        }

        if (!$miner_info) {
            $this->ajaxError(array("error" => "抱歉，暂时没有该矿机！"));
        }

        if ($user_info['id'] == $to_user_info['id']) {
            $this->ajaxError(array("error" => "抱歉，不能给自己转赠矿机！"));
        }


        if ($world_level['buy_rights']) {
            $buy_rights = explode(',', $world_level['buy_rights']);
            if (!in_array($miner_info['miner_id'], $buy_rights)) {
                $this->ajaxError(array("error" => "被转赠用户等级不足以使用该矿机！"));
            }
        } else {
            $this->ajaxError(array("error" => "被转赠用户等级不足以使用该矿机！"));
        }

        if ($user_info['dc_coin'] < $miner_info['miner_price']) {
            $this->ajaxError(array("error" => "余额不足！"));
        }

        $order_sn=$this->get_order_sn();
        //插入订单
        $miner_order_data = array(
            'order_sn' => $order_sn,
            'user_id' => $user_info['id'],
            'to_user_id' => $to_user_info['id'],
            'type' => 2,
            'order_status' => 1,
            'pay_status' => 1,
            'goods_price' => $miner_info['miner_price'],
            'total_amount' => $miner_info['miner_price'],
            'order_amount' => $miner_info['miner_price'],
            'mobile' => $user_info['account'],
            'pay_code' => 'DC支付',
            'pay_name' => 'DC支付',
            'add_time' => time(),
            'confirm_time' => time(),
            'pay_time' => time(),
            'is_distribut' => 0,
            'deleted' => 0
        );
        Db::startTrans();
        try {
            $order_id = M('world_order')->add($miner_order_data);
            if (!$order_id) {
                Db::rollback();
                $this->ajaxError(array("error" => "订单插入失败！"));
            }

            //订单商品列表
            $GoodsOrderData = array(
                'order_id' => $order_id,
                'miner_id' => $miner_info['miner_id'],
                'miner_name' => $miner_info['miner_name'],
                'original_img' => $miner_info['original_img'],
                'miner_num' => 1, //购买数量暂时默认1
                'miner_price' => $miner_info['miner_price'],
                'release_size' => $miner_info['release_size'],
                'release_rate' => $miner_info['rebate_rate'],
                'scrap_days' => $miner_info['scrap_days'],
                'sort' => $miner_info['sort']
            );
            $GoodId = M('world_order_goods')->add($GoodsOrderData);


            //dc币明细表
            $world_dc_log = array(
                'user_id' => $user_info['id'],
                'number' => '-' . $miner_info['miner_price'],
                'before' => $user_info['dc_coin'],
                'after' => $user_info['dc_coin'] - $miner_info['miner_price'],
                'change_time' => time(),
                'desc' => '转出矿机-'.$miner_info['short_name'],
                'order_id' => $order_id,
                'type' => 5
            );
            $dc_log_result = M('world_dc_log')->add($world_dc_log);

            //插入用户矿机列表
            $miner_data[] = array(
                'order_id' => $order_id,
                'user_id' => $to_user_info['id'],
                'miner_id' => $miner_info['miner_id'],
                'miner_price' => $miner_info['miner_price'],
                'scrap_days' => $miner_info['scrap_days'],
                'release_rate' => $miner_info['rebate_rate'],
                'income_all' => $miner_info['release_size'],
                'income_surplus' => $miner_info['release_size'],
                'add_time' => time(),
                'update_time' => time(),
                'status' => 1,
                'source_type' => 3,
                'sort' => $miner_info['sort']
            );
            //赠送矿机获得
            $presented = M('world_miner')->where(array('type' => 2))->order('sort desc')->find();
            if ($presented && $presented['is_on_sale'] == 1 && $presented['is_del'] == 0) {
                $miner_data[] = array(
                    'order_id' => $order_id,
                    'user_id' => $user_info['id'],
                    'miner_id' => $presented['miner_id'],
                    'miner_price' => $presented['miner_price'],
                    'scrap_days' => $presented['scrap_days'],
                    'release_rate' => $presented['rebate_rate'],
                    'income_all' => $miner_info['release_size_d02'],
                    'income_surplus' => $miner_info['release_size_d02'],
                    'add_time' => time(),
                    'update_time' => time(),
                    'status' => 1,
                    'source_type' => 2,
                    'sort' => $presented['sort'],
                );
            }

            $miner_result = M('world_users_miner')->insertAll($miner_data);


            //转赠矿机算力
            $res=accountPowerLog($to_user_info['id'], $miner_info['release_size'], '采购矿机-'.$miner_info['short_name'], $order_id, $order_sn,3, 1,0);
            //转赠赠送矿机算力
            $res=accountPowerLog($user_info['id'], $miner_info['release_size_d02'], '推广奖励-'.$presented['short_name'], $order_id, $order_sn,4, 1,0);

            //数据生成成功扣费
            if ($miner_result) {

                //升级系统
                $WorldLogic = new \app\common\logic\WorldLogic();
                $WorldLogic->UpgradeMethod($to_user_info);

                $result_one = M('member')->where(array('id' => $user_info['id']))->setDec('dc_coin', $miner_info['miner_price']);
                message_notificat($to_user_info['id'],2,'矿机转入','他人转入' .$miner_info['short_name'] . '矿机，已转化成对应算力',$order_id);
                // if($result) $this->ajaxSuccess(array('msg' => "激活成功！"));
                if ($result_one) {
                    // 上线矿机加速释放
                    $result_two = calculation_accelerate($order_id);
                    if ($result_two) {
                        Db::commit();
                        $this->ajaxSuccess(array('msg' => "转出成功!"));
                    } else {
                        Db::rollback();
                        $this->ajaxError(array("error" => "加速释放失败!"));
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError(array("error" => "扣费失败!"));
                }

            } else {
                Db::rollback();
                $this->ajaxError(array("error" => "矿机生成失败！"));
            }
        } catch (Exception $e) {
            Db::rollback();
        }
    }

    /**
     * 获取订单 order_sn
     * @return string
     */
    public function get_order_sn()
    {
        $order_sn = null;
        // 保证不会有重复订单号存在
        while (true) {
            $order_sn = date('YmdHis') . rand(1000, 9999); // 订单编号
            $order_sn_count = M('order')->where("order_sn = " . $order_sn)->count();
            if ($order_sn_count == 0)
                break;
        }
        return $order_sn;
    }

    /**
     * 获取DC交易订单号 order_sn
     * @return string
     */
    public function get_trade_sn()
    {
        $order_sn = null;
        // 保证不会有重复订单号存在
        while (true) {
            $order_sn = date('YmdHis') . rand(1000, 9999); // 订单编号
            $order_sn_count = M('world_dc_trade')->where("order_sn = " . $order_sn)->count();
            if ($order_sn_count == 0)
                break;
        }
        return $order_sn;
    }

}