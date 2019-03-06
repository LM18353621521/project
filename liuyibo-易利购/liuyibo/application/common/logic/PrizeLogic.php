<?php

namespace app\common\logic;

use think\db;
use think\Log;

class PrizeLogic
{
    // 注意提示，房车奖的金额是写死

    // 需序列化奖项
    private $prize_serialize = ['recommended_prize'];

    // 奖项需要验证身份等级
    private $prize_identity = [
        'first_prize' => [2,3,4,5,6],
        'team_prize' => [3,4,5,6],
        'discount_prize' => [2,3,4,5,6],
        'recommended_prize' => [4,5,6]
    ];

    // 需要验证订单的奖项
    private $prize_order = [
        'first_prize',
        'team_prize',
        'market_prize',
        'recommended_prize',
        'general_prize'
    ];

    // 需要用户信息的奖项
    private $prize_user = [
        'first_prize',
        'team_prize',
        'car_home_prize',
        'discount_prize',
        'recommended_prize',
        'general_prize'
    ];

    // 需要验证用户的奖项
    private $prize_user_verify = [
        'first_prize',
        'team_prize',
        'discount_prize'
    ];

    // account_log 奖项type
    private $prize_type = [
        'market_prize' => 3,
        'first_prize' => 4,
        'team_prize' => 5,
        'recommended_prize' => 6,
        'general_prize' => 7,
    ];

    private $month_arr = [
        1 => 31,
        2 => [28,29],
        3 => 31,
        4 => 30,
        5 => 31,
        6 => 30,
        7 => 31,
        8 => 31,
        9 => 30,
        10 => 31,
        11 => 30,
        12 => 31,
    ]; 

    /**
     * 奖项校验 
     */
    public function prize($id, $prize_info, $order_id = 0, $steps = 1,$goods = []){
        if (!$id) {
            return false;
        }
        // 查看身份 获取对应的奖项
        $prize_user = $this->prize_user;
        $prize_user_verify = $this->prize_user_verify;
        $prize_order = $this->prize_order;
        $prize_type = $this->prize_type;
        $prize_identity = $this->prize_identity;

        // 需要用户信息的奖项触发
        if (in_array($prize_info,$prize_user)) {
            if (!$id && !$prize_info) {
                log_info('prize_log',$prize_info.'参数异常','prize');
                return false;
            }
            $user_info = Db::name('users')
                            ->field('user_id,level,first_leader,second_leader,third_leader')
                            ->where(['user_id' => $id])
                            ->find();
        }

        // 需要校验用户身份的奖项触发
        if (in_array($prize_info,$prize_user_verify)) {
            $identity_arr =  $prize_identity[$prize_info];
            if (!in_array($user_info['level'],$identity_arr)) {
                log_info('prize_log',$prize_info.'身份不符合','prize');
                return false;
            }
        }

        // 校验订单存在 
        if (in_array($prize_info,$prize_order)) {
            if ($steps == 1)  {
                $order_res = Db::name('order')->where(['order_id' => $order_id])->find();
                if (!$order_res){
                    log_info('prize_log',$prize_info.'无该订单信息','prize');
                    return false;
                }

                $log = Db::name('account_log')->where(['order_id' => $order_id, 'type' => $prize_type[$prize_info]])->find();
                // dump(Db::name('account_log')->getLastSql());exit;
                if ($log) {
                    log_info('prize_log','奖项已处理','prize');
                    return false;
                }
            }

        }
        switch ($prize_info){
            case 'first_prize':
                // 寻找上一级   直推奖直接用
                $first_leader_info = Db::name('users')->field('level,user_id,first_leader')->where(['user_id' => $user_info['first_leader']])->find();
                $leader_info['user_id'] = $first_leader_info['user_id'];
                $leader_info['order_id'] = $order_res['order_id'];
                $leader_info['check'] = 1;
                $first_prize_switch = $this->prize_switch($prize_info.'-'.$user_info['level'], $prize_info, $leader_info);
                if ($first_prize_switch) {
                    // 直推奖项开启
                    $res['first_prize'] = $this->first_prize($leader_info,$first_prize_switch['config'], $prize_info);
                } else {
                    return $first_prize_switch;
                }
                // 执行团队奖
                if ($res['first_prize']) {
                    $res['team_prize'] = $this->prize($user_info['user_id'], 'team_prize', $order_id);
                }
                // 执行市场补助 基金添加
                if ($res['team_prize']) {
                    $res['market_prize'] = $this->prize($user_info['user_id'], 'market_prize', $order_id);
                }
                log_info('prize_log',$prize_info.'奖励信息'.$res,'prize');
                return $res;

                break;

            case 'team_prize':
                // 获取满足 3 4 5 6 身份等级 ，并且奖项已经开启  离最近的一个人
                $leader_info = recursive($user_info['user_id'],[3,4,5,6],$prize_info);
                if ($leader_info['code'] < 0) return ['code' => -1, 'msg' => $leader_info['msg']];
                $leader_info['money'] = $order_res['order_amount'];
                $leader_info['order_id'] = $order_res['order_id'];
                $leader_info['check'] = 1;

                // 待处理
                $team_prize_switch = $this->prize_switch($prize_info.'-'.$leader_info['level'], $prize_info, $leader_info);
                if ($team_prize_switch) {
                    // 直推奖项开启
                    $res = $this->team_prize($leader_info,$team_prize_switch['config'], $prize_info);
                    log_info('prize_log',$prize_info.'奖励信息'.$res,'prize');
                    return $res;
                } else {
                    log_info('prize_log',$prize_info.'-'.$leader_info['level'].'折扣奖尚未开启','prize');
                    return $team_prize_switch;
                }

                break;

            case 'discount_prize':
                if (!$goods) return false;
                $result = $this->prize_switch($prize_info.'-'.$user_info['level'], $prize_info, ['check' => 1]);
                if ($result){
                    log_info('prize_log',$prize_info.'折扣奖尚未开启','prize');
                    return $goods['shop_price'];
                } else {
                    $discount = $this->discount_prize($user_info['user_id'], $result['config'],$goods);
                    return $discount;
                }
                break;

            case 'recommended_prize':
                $leader_info = Db::name('users')
                            ->field('user_id,level,first_leader,second_leader')
                            ->where(['user_id' => ['in', [$user_info['first_leader'], $user_info['second_leader']]]])
                            ->select();

                $result = $this->prize_switch($prize_info.'-'.$user_info['level'], $prize_info, ['check' => 1]);
                if (!$result['code']) {
                    log_info('prize_log',$prize_info.'奖励信息'.$result,'prize');
                    return false;
                }
 
                // 奖项开启
                $info['order_id'] = $order_res['order_id'];
                $info['user_id'] = $user_info['user_id'];
                $info['prize_info'] = $prize_info;
                $res = $this->recommended_prize($leader_info,$result['config'],$info);
                break;

            case 'management_prize':
                //未处理
                $res = $this->management_prize();
                break;
            case 'market_prize':
                if ($steps == 1) {
                    // 存在订单编号，基金池添加基金
                    // 查询有无该订单记录
                    $info['user_id'] =  $order_res['user_id'];
                    $info['order_id'] =  $order_res['order_id'];
                    $info['order_sn'] =  $order_res['order_sn'];
                    $res = $this->prize_switch($prize_info.'-2', $prize_info,$info);
                    // 添加基金
                } else {
                    // 分发奖池
                    $result = $this->prize_switch($prize_info.'-2', $prize_info,['check' => 1]);
                    if (!$result['code'])
                        return $result;
                    else 
                        // 奖项开启
                       $res =  $this->market_prize($result['config']);

                }
                log_info('prize_log',$prize_info.'奖励信息'.$res,'prize');
                return $res;
                break;
            case 'car_home_prize':
                if ($steps == 1) {
                    $result = $this->prize_switch($prize_info.'-'.$user_info['level'], $prize_info,['check' => 1]);
                    if (!$result['code'] < 0) {
                        log_info('prize_log',$prize_info.'奖励信息'.$result,'prize');
                        return false;
                    }
                    log_info('prize_log',$prize_info.'奖励信息'.$result,'prize');
                    return true;
                } else {
                    $result = $this->prize_switch($prize_info.'-'.$user_info['level'], $prize_info);
                    if (!$result) {
                        log_info('prize_log',$prize_info.'奖励信息'.$result,'prize');
                        return false;
                    }

                    // 奖项开启
                    $money = input('get.money', 20000);
                    $res =  $this->car_home_prize($user_info, $money,$result['config']);
                    log_info('prize_log',$prize_info.'奖励信息'.$res,'prize');
                    return false;
                }
        

                break;
            case 'general_prize':
            // 未处理完
                //  普通分销奖
                dump($order_id);
                dump($user_info);
                $result = $this->prize_switch($prize_info.'-'.$user_info['level'], $prize_info, ['check' => 1]);
                dump($result);
                
                $res =  $this->general_prize($user_info,$result['config'],$order_id);
                dump($res);
                exit;
                break;
            case  'areas_share_prize':
                break;
            case 'special_prize':
                break;
            default:
                break;
        }
    }

    /**
     * 奖项开关校验
     */
    private  function prize_switch($prize_name,$prize_info, $info = []){
        $config = distributCache($prize_name);
        // 需序列化的奖项
        if (in_array($prize_info, $this->prize_serialize)) {
            $config = unserialize($config[0]);
        }

        if ($prize_info == 'general_prize') {
            $config['general_prize_level'] = unserialize($config['general_prize_level']);
        }
        if ($config[$prize_info.'_'.'switch']) {

            if ($info['check']) {
                // 只检验是否开启
                return ['code' => 1, 'msg' => '奖项已开启', 'config' => $config];
            }
            // 处理奖项
            // 直推奖
            if ($prize_info == 'market_prize') {
                $prize_begin_time = Db::name('distribut_system')->where(['inc_type' => 'settlement', 'name' => 'prize_begin_time'])->value('value');
                if ($prize_begin_time > time()){
                    log_info('prize_log',$prize_info.'奖励信息'.$res,'prize');
                    Log::info($prize_info.'基金池异常');
                    return false;
                }
                $funds_res = Db::name('distribut_system')
                            ->where(['inc_type' => 'settlement', 'name' => 'funds'])
                            ->setInc('value', $config['market_prize_money']);
                if (!$funds_res) {
                    log_info('prize_log',$prize_info.'基金池异常'.$funds_res,'prize');
                    return false;
                }
                // 奖项流水记录
                accountLog(
                    $info['user_id'],
                    $config['market_prize_money'], 
                    0, 
                    '基金池补助:'.$config['market_prize_money'].'元', 
                    $config['market_prize_money'], 
                    $info['order_id'], 
                    $info['order_sn'],
                    3
                );
                log_info('prize_log',$prize_info.'基金添加成功'.$funds_res,'prize');
                return true;
            }

        } else {
            // 开关关闭
            return ['code' => -1, 'msg' => '奖项尚未开启'];
            log_info('prize_log',$prize_info.'该奖项已关闭'.$funds_res,'prize');
            return false;
        }
    }

    /**
     * 直推奖
     * @param int $first_leader 添加金额的用户
     * @param arr $config 奖项配置
     * @return arr 状态结果
     */
    private function first_prize($first_info, $config, $prize_info){
        $user_id = $first_info['user_id'];
        // 对用户佣金分发以及记录
        if (empty($user_id) && !is_int($user_id)) {
            log_info('prize_log',$prize_info.'用户参数异常'.$user_id,'prize');
            return false;
            
        }

        // 记录流水
        $desc = '直推用户id:'.$id.','.$prize_info.'奖励'.$config['first_prize_fee'];
        accountLog($user_id,$config['first_prize_fee'],0, $desc,$config['first_prize_fee'],$first_info['order_id'],0,4);
        log_info('prize_log',$prize_info.'奖励已分发'.$user_id,'prize');
        return true;
    }

    /**
     * 团队奖
     * @param int $first_level 添加金额的用户
     * @param arr $config 奖项配置
     * @return arr 状态结果
     */
    private function team_prize($team_info, $config, $prize_info){
        $user_id = $team_info['user_id'];
        // dump($team_info);exit;
        // 对用户佣金分发以及记录
        if (empty($user_id) && !is_int($user_id)) {
            log_info('prize_log',$prize_info.'用户参数异常'.$user_id,'prize');
            return false;
        }
        $unit = $config['team_prize_unit'];
        if ($unit == 1) {
            // 按金额为单位
            $team_prize_money = $config['team_prize_money'];
        } else {
            // 按比例来算
            $team_prize_money =  $config['team_prize_money'] * $team_info['money'] / 100;
        }
        // 记录流水
        $desc = '直推用户id:'.$user_id.','.$prize_info.'奖励'.$team_prize_money;
        accountLog($user_id,$team_prize_money,0, $desc,$team_prize_money,$team_info['order_id'],0,5);
        log_info('prize_log',$prize_info.'奖励已分发'.$user_id,'prize');
        return true;
    }


    private function special_prize(){

    }

    /**
     * 管理奖
     * @return [type] [description]
     */
    private function management_prize(){}

    /**
     * 市场补助奖  分奖
     */
    private function market_prize($config){
        //  获取上一次分红时间
        $distribut = Db::name('account_log')
                        ->where(['type' => 3])
                        ->find();
        $unit = $config['market_prize_time_unit'];
        if ($distribut) {
            switch ($unit) {
                case 1:
                    $time_unit = 3600*24 * $config['market_prize_time'] + $distribut['change_time'];
                    break;
                case 2:
                    $time_unit = 3600*24 * 7  * $config['market_prize_time'] + $distribut['change_time'];
                    break;
                case 3:
                    $time_unit = $this->month_deal($distribut['change_time']);
                    break;
                case 4:
                    $time_unit = $this->year_deal($distribut['change_time']);
                    break;
                default:
                    $time_unit = 3600*24 * $config['market_prize_time'];
                    break;
            }

            if ($time_unit < time()){
                log_info('prize_log',$prize_info.'市场补助尚未到达分奖时间','prize');
                return false;
            }
        } else {
            $prize_begin_time = Db::name('distribut_system')->where(['inc_type' => 'settlement', 'name' =>  'prize_begin_time'])->value('value');
            if (!$prize_begin_time) {
                log_info('prize_log',$prize_info.'市场补助尚未到达分奖时间','prize');
                return false;
            }
            //无分奖记录 首次分奖
            switch ($unit) {
                case 1:
                    $time_unit = 3600*24 * $config['market_prize_time'] + $prize_begin_time;
                    break;
                case 2:
                    $time_unit = 3600*24 * 7  * $config['market_prize_time'] +$prize_begin_time;
                    break;
                case 3:
                    $time_unit = $this->month_deal($prize_begin_time);
                    break;
                case 4:
                    // 年 待处理
                    $time_unit = $this->year_deal($prize_begin_time);
                    break;
                default:
                    $time_unit = 3600*24 * $config['market_prize_time'];
                    break;
            }
            if ($time_unit > time()){
                log_info('prize_log',$prize_info.'市场补助尚未到达分奖时间','prize');
                return false;
            }
        }
        // dump($config);
        $top = $config['market_prize_top'];
        //开始分奖 
        // 获取基金池
        $funds_money = Db::name('distribut_system')
                        ->where(['inc_type' => 'settlement', 'name' => 'funds'])
                        ->value('value');
        if ($funds_money <= 0 ){
            log_info('prize_log',$prize_info.'奖池为零','prize');
            return false;
        }
        // 获取参与分奖的会员
        $user_arr = Db::name('users')->field('user_id')->where(['level' => 2])->select();
        // 去除已补助完毕的人
        foreach ($user_arr as $key => $value) {

            //  统计已分多少佣金
            $user_money = Db::name('account_log')
                        ->where(['user_id' => $value['user_id'], 'type' => 3])
                        ->sum('user_money');
            // 当前用户 结束基金补助
            if ($user_money >= $top){
                unset($user_arr[$key]);
                continue;
            }
            $user_arr[$key]['distribut_money'] = $user_money;
        }

        $user_num = count($user_arr);
        if (!$user_num) {
            log_info('prize_log',$prize_info.'无参与人员信息','prize');
            return false;
        }
        $user_prize_money = round($funds_money / $user_num, 2);
        if ($user_prize_money <= 0 ){
            log_info('prize_log',$prize_info.'奖励过低','prize');
            return false;
        }
        foreach ($user_arr as $key => $value) {

            if ($value['distribut_money'] + $user_prize_money  > $top) {
                //  加上补助 上限超额的用户
                $now_money_prize = $top - $value['distribut_money']; // 距离上限的金额
                $funds_money -= $now_money_prize;

                accountLog(
                    $value['user_id'], 
                    $now_money_prize, 
                    0,
                    '本次基金补助1:'.$now_money_prize.'元', 
                    $now_money_prize, 
                    0,
                    0,
                    3
                );
            } else {
                $funds_money -= $user_prize_money;
                accountLog(
                    $value['user_id'], 
                    $user_prize_money, 
                    0,
                    '本次基金补助2:'.$user_prize_money.'元', 
                    $user_prize_money, 
                    0,
                    0,
                    3
                );
            }
        }

        //判断  有剩余基金 返回基金池  等下一次开启
        if ($funds_money > 0) {
            $res  = Db::name('distribut_system')
                        ->where(['inc_type' => 'settlement', 'name' => 'funds'])
                        ->save(['value' => round($funds_money, 2)]);
        } else {
            // 奖池归零
            $res  = Db::name('distribut_system')
                        ->where(['inc_type' => 'settlement', 'name' => 'funds'])
                        ->save(['value' => 0]);
        }

        if ($res){
            log_info('prize_log',$prize_info.'本次补助完毕','prize');
            return true;
        } else {
            log_info('prize_log',$prize_info.'本次补助完毕,奖池异常','prize');
            return true;
        }

    }

    private function areas_share_prize(){}

    /**
     * 房车奖
     * @return [type] [description]
     */
    private function car_home_prize($user_info ,$money,$config){
        if ($money >= $config['car_home_money']) {
            $integral = $money * $config['car_home_integral'] / 100;
            // echo 1;
            // 更新 用户积分
             accountLog(
                $user_info['user_id'], 
                0, 
                $integral,
                '本次房车奖：'.$integral.'积分', 
                0, 
                0,
                0,
                2
            );
            return true;
        } else {
            log_info('prize_log',$prize_info.'未满足条件','prize');
            return false;
        }

    }

    private function discount_prize($user_id,$config, $goods){
        //  查看是否开启复购
        if ($config['discount_prize_repeat_buy']){
            // 开启复购
            echo 1;
            $order_count = Db::name('order')->where(['user_id' => $user_id])->count();
            if ($order_count < 1) return $goods['shop_price'];
        }
        switch ($config['discount_prize_goods_status']) {
            case 0:  // 只算普通商品
                if ($goods['type_id']) return $goods['shop_price'];
                // 当前商品不是身份产品  按当前折扣
                return $goods['shop_price'] * $config['discount'] / 100;
                break;
            case 1: // 只算身份产品
                if ($goods['type_id']) return  $goods['shop_price'] * $config['discount'] / 100;
                return $goods['shop_price'];
                break;
            case 2: //全部
                return $goods['shop_price'] * $config['discount'] / 100;
                break;
            default:
                return $goods['shop_price'];
                break;
        }
    }

    /**
     * 普通产品分销  
     * @return [type] [description]
     */
    private function general_prize($user_info, $config, $order_id){
        //  查看 含有多少层级
        $len =  $config['general_prize_set_level'];
        $level = $config['general_prize_level'];
        //  获取订单消费金额
        $order_money = Db::name('order')
                        ->where(['order_id' => $order_id])
                        ->value('order_amount');
        // 寻找对应层级
        switch ($len) {
            case 1:
                $first_leader = $user_info['first_leader'];
                $district_money = $order_money * $level[$len] / 100;
                accountLog(
                    $first_leader,
                    $district_money,
                    0,
                    '普通订单(ID:'.$order_id.')分销一层,金额:'.$district_money.'元',
                    $district_money,
                    $order_id,
                    0,
                    7                    
                );
                break;
            case 2:
                $first_leader = $user_info['first_leader'];
                $district_money = $order_money * $level[$len-1] / 100;
                accountLog(
                    $first_leader,
                    $district_money,
                    0,
                    '普通订单(ID:'.$order_id.')分销一层,金额:'.$district_money.'元',
                    $district_money,
                    $order_id,
                    0,
                    7                    
                );
                $second_leader = $user_info['second_leader'];
                $district_money = $order_money * $level[$len] / 100;
                accountLog(
                    $second_leader,
                    $district_money,
                    0,
                    '普通订单(ID:'.$order_id.')分销二层,金额:'.$district_money.'元',
                    $district_money,
                    $order_id,
                    0,
                    7                    
                );
                break;
            case 3:
                $first_leader = $user_info['first_leader'];
                $district_money = $order_money * $level[$len-2] / 100;
                accountLog(
                    $first_leader,
                    $district_money,
                    0,
                    '普通订单(ID:'.$order_id.')分销一层,金额:'.$district_money.'元',
                    $district_money,
                    $order_id,
                    0,
                    7                    
                );
                $second_leader = $user_info['second_leader'];
                $district_money = $order_money * $level[$len-1] / 100;
                accountLog(
                    $second_leader,
                    $district_money,
                    0,
                    '普通订单(ID:'.$order_id.')分销二层,金额:'.$district_money.'元',
                    $district_money,
                    $order_id,
                    0,
                    7                    
                );
                $third_leader = $user_info['third_leader'];
                $district_money = $order_money * $level[$len] / 100;
                accountLog(
                    $third_leader,
                    $district_money,
                    0,
                    '普通订单(ID:'.$order_id.')分销三层,金额:'.$district_money.'元',
                    $district_money,
                    $order_id,
                    0,
                    7                    
                );
                break;    
            default:
                log_info('prize_log',$prize_info.'无层级信息','prize');

                return false;
                break;
        }
        log_info('prize_log',$prize_info.'普通产品奖项已处理','prize');
        return true;
    }

    private function recommended_prize($leader_info, $config,$info){
        $step = 1;
        $len = count($config['recommend_identity']);
        for ($i = 0; $i<$len; $i++){
            $prize_arr[$i]['recommend_identity'] = $config['recommend_identity'][$i];
            $prize_arr[$i]['recommend_money'] = $config['recommend_money'][$i];
            $prize_arr[$i]['recommendlevel'] = $config['recommendlevel'][$i];
        }
        foreach($leader_info as $key => $value){
            $user_id = $value['user_id'];
            foreach ($prize_arr as $k => $val) {
                if ($value['level'] == $val['recommend_identity'] && $step == $val['recommendlevel']) {
                    $recommended_money = $val ['recommend_money'];
                }
            }
            // 对用户佣金分发以及记录
            if (empty($user_id) && !is_int($user_id)) {
                log_info('prize_log',$prize_info.'用户ID异常'.$user_id,'prize');
                return false;
            }
            // 如果当前用户等级为会员
            if ($value['level'] == 2) {
                // 待处理
            } else {
                $desc = $step.'推荐用户id:'.$info['user_id'].','.$info['prize_info'].'奖励'.$recommended_money;
                accountLog($user_id,$recommended_money,0, $desc,$recommended_money,$info['order_id'],0,6);
            }
            // 记录流水
            $step++;
        }
        log_info('prize_log',$prize_info.'推荐用户'.$info['user_id'].'奖励已分发','prize');
        return true;
    }

    /*
        获取下一个间隔月的时间戳
     */
    private function month_deal($distribut_time){
        $month_arr = $this->$month_arr;
        $last_his = date('H:i:s', $distribut_time);
        $last_day = date('d', $distribut_time);
        $last_year = date('Y', $distribut_time);
        $last_month = date('m', $distribut_time);
        $next_month = $last_month + $config['market_prize_time'];
        // 查看目标月 有无当日
        if ($next_month == 2) {
            if ($last_day > $month_arr[0] ) {
                //  超过 28  判断当前年是否是闰年
                $time = mktime(20,20,20,4,20,$last_year);
                if (date("L",$time)==1){
                    // 闰年
                    $next_time = $last_year.'-2-'.$month_arr[1].' '.$last_his;
                } else {
                    $next_time = $last_year.'-2-'.$month_arr[0].' '.$last_his;
                }
            }
        } elseif ($next_month >12 ) {
            // 第二年
            // $next_month -= 12;
            $year_num = $next_month / 12;
            $next_month = $next_month % 12;
            $last_year += $year_num;
            if ($next_month == 2) {
                if ($last_day > $month_arr[0] ) {
                    //  超过 28  判断当前年是否是闰年
                    $time = mktime(20,20,20,4,20,$last_year);
                    if (date("L",$time)==1){
                        // 闰年
                        $next_time = $last_year.'-2-'.$month_arr[1].' '.$last_his;
                    } else {
                        $next_time = $last_year.'-2-'.$month_arr[0].' '.$last_his;
                    }
                }
            } elseif ($last_day >  $month_arr[$next_month]) {
                $next_time = $last_year.'-'.$next_month.'-'.$month_arr[$next_month].' '.$last_his;
            } else {
                $next_time = $last_year.'-'.$next_month.'-'.$last_day.' '.$last_his;
            }
        }
        return  strtotime($next_time);
    }

    public function year_deal($distribut_time){
        $last_his = date('H:i:s', $distribut_time);
        $last_day = date('d', $distribut_time);
        $last_year = date('Y', $distribut_time);
        $last_month = date('m', $distribut_time);
        $next_year = $last_year + $config['market_prize_time'];
        if ($last_month == 2 && $last_day == 29) {
            $last_day = 28;
        }

        $next_time = $next_year.'-'.$last_month.'-'.$last_day.' '.$last_his;
        return strtotime($next_time);
    }

    public function test(){
        $res = $this->prize(1,'first_prize',14);
        dump($res);
    }

    public function confirm_market_prize(){
        $res = $this->prize(1,'market_prize',14);
    }
}