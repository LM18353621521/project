<?php

namespace app\common\logic;

use app\common\model\DistributPrize;
use app\common\model\Order;
use app\common\model\Users;
use app\common\util\Log;
use think\Db;
use think\Model;
class DistributPrizeLogic extends Model
{

    protected $data = array();//data
    protected $config;//data
    protected $user;//data
    protected $user_id = 0;//user_id
    protected $log_name = ROOT_PATH."runtime/log/distribut.log";
    private   $logObj;
    private   $wechat;
    private $distribut_price = 0;
    private $contact_order = '';
    private $user_id_list = [];


    public function __construct()
    {
        parent::__construct();
        $this->logObj = new Log();
        $this->wechat = new \app\common\logic\WechatLogic;
    }

    /**
     * 设置商品ID
     * @param $user_id
     */
    public function setOrderId($order_id)
    {
        $this->order_id = $order_id;
    }

    /**
     * 设置用户ID
     * @param $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;

        //查询当前用户
        $this->user = Db::name('users')
            ->field('user_id,level,first_leader,second_leader,third_leader')
            ->where(['user_id' => $this->user_id])
            ->find();
    }

    /**
     * 设置用户ID
     * @param $user_id
     */
    public function setConfigCache($Method)
    {
        //获取当前奖项所有配置信息
        $Distribut = new DistributPrize();
        $this->config = $Distribut->configCache($Method,$this->user['level']);
    }

    /**
     * 分销规则
     * @param
     */
    public function distribut($Method,$data = ''){
        $DistributPrizeLogic = new DistributPrizeLogic();
        //验证方法
        if(!$this->check($Method,$DistributPrizeLogic)){return false;}
        foreach($Method as $key => $val){
            $this->$val($data);
            continue;
        }
    }
    /**
     * 校验
     * @param array
     */
    public function check($Method,$obj){
        foreach($Method as $key => $val){
            if(!method_exists($obj,$val)){
                return false;
            }
        }
        return true;
    }
    /**
     * 直推奖
     * @param
     */
    public function first_prize(){
        if(!$this->config['first_prize_switch']){
            return false;
        }
        try{
            // 订单信息
            $order_info = $this->getOnselfTypeOrder($this->user['user_id']);
            $desc = '直推奖获得'.$this->config['first_prize_fee'].'元';
            $wechat = $this->wechat;
            $wechat->sendTemplateMsgPrize($this->user['first_leader'], '直推奖', $this->config['first_prize_fee'] );

            accountLog($this->user['first_leader'],$this->config['first_prize_fee'],0, $desc,$this->config['first_prize_fee'],$order_info['order_id'],$order_info['order_sn'],4);

        }catch(\Exception $e)
        {
            $this->logObj->log_result($this->log_name, "【用户参数异常】:\n" . $this->user_id . "\n");
        }
        return true;
    }
    /**
     * 团队奖
     * @param array
     */
    public function team_prize(){
        try{
            // 获取大于当前user.level等级的信息
            $levels = Db::name('user_level')->field('level_id')->where('level_id', 'GT', $this->user['level'])->select();
            $levels = array_column($levels,'level_id');
            // 获取满足 3 4 5 6 身份等级 ，并且奖项已经开启  离最近的一个人
            $limit_level = [4,5,6];
            $leader_info = $this->recursive($this->user_id, $levels,$limit_level);
            if (!$leader_info) return false;

            $user_info = Users::get($leader_info['user_id']);
            $Distribut = new DistributPrize();
            $res = $Distribut->configCache('team_prize',$leader_info['level']);
            // 获取对应身份的团队奖
            if (!$res['team_prize_switch'])return false;
            //  奖项单位
            if ($res['team_prize_unit']) {
                // 按金额为单位
                $team_prize_money = $res['team_prize_money'];
            } else {
                // 按比例来算
                return false;
                exit;
            }
            // 订单信息
            $order_info = $this->getOnselfTypeOrder($this->user['user_id']);
            $desc = '团队奖获得'.$res['team_prize_money'].'元';
            $wechat = $this->wechat;
            $wechat->sendTemplateMsgPrize($leader_info['user_id'], '团队奖', $res['team_prize_money']);
            accountLog($leader_info['user_id'],$team_prize_money,0, $desc,$team_prize_money,$order_info['order_id'],$order_info['order_sn'],5);
            //  还原user_id
        } catch (\Exception $e)
        {
            $this->logObj->log_result($this->log_name, "【团队奖异常】:\n" . $this->user_id . "\n");
        }
        return true;
    }

    /**
     * 市场补助奖  分奖
     */
    public  function market_prize(){
        try{
            $funds_res = Db::name('distribut_system')
                ->where(['inc_type' => 'settlement', 'name' => 'funds'])
                ->setInc('value', $this->config['market_prize_money']);
        }catch(\Exception $e)
        {
            $this->logObj->log_result($this->log_name, "【市场补助奖异常】:\n"  . "\n");
        }
    }

    /**
     * 推荐奖
     * */
    public function recommended_prize(){
        // 反序列化配置信息
        if ($this->config) {
            $this->config = unserialize($this->config[0]);
        }
        // 校验开关  还原数组结构
        if (!$this->config['recommended_prize_switch']) {
            return false;
        } else {
            $len = count($this->config['recommend_identity']);
            for ($i = 0; $i<$len; $i++){
                $this->config[$this->config['recommend_identity'][$i]]['recommend_identity'] = $this->config['recommend_identity'][$i];
                $this->config[$this->config['recommend_identity'][$i]]['first_leader'] = $this->config['first_leader'][$i];
                $this->config[$this->config['recommend_identity'][$i]]['second_leader'] = $this->config['second_leader'][$i];
            }
            unset($this->config['recommend_identity']);
            unset($this->config['recommendlevel']);
            unset($this->config['recommended_prize_switch']);
            unset($this->config['first_leader']);
            unset($this->config['second_leader']);
            unset($this->config['recommend_money']);
        }

        $wechat = $this->wechat;
        Db::startTrans();
        try {

            $this->recommendedDistribut($this->user['first_leader'], 'first_leader', $wechat);
            $this->recommendedDistribut($this->user['second_leader'], 'second_leader', $wechat);

            Db::commit();
        } catch (\Exception $e){
            $this->logObj->log_result($this->log_name, "【推荐奖异常】:\n"  . "\n");
        }
    }

    private function recommendedDistribut($user_id, $level,$wechat){
        // 获取推荐人信息
        $user_info = Users::get($user_id);
        if (empty($user_info) || !$user_info['is_distribut']) {
            return false;
        }

        //  校验是否设置该身份层级奖项信息
        $key_arr = array_keys($this->config);

        if (!in_array($user_info['level'], $key_arr)) {
            return false;
        }

        // 冻结资金
        $distribut_money =  $this->config[$user_info['level']][$level];

        // 更新用户冻结资金字段
        Db::name('users')->where(['user_id' => $user_id])->setInc('frozen_money',$distribut_money);

        $desc = '推荐奖获得:'.$distribut_money.'元';
        /* 插入帐户变动记录 */
        $account_log = array(
            'user_id' => $user_id,
            'user_money' => 0,
            'frozen_money' => $distribut_money,
            'pay_points' => 0,
            'change_time' => time(),
            'desc' => $desc,
            'order_id' => 0,
            'order_sn' => 0,
            'type' => 6
        );
        $wechat->sendTemplateMsgPrize($user_id,'推荐奖',$distribut_money);

        Db::name('account_log')->add($account_log);

    }

    /**
     * 解冻冻结金额   登录时触发
     */
    public function frozen_money($user_id,$action=''){
        $user_info = M('users')->field('user_id,level,user_money,frozen_money')->where(['user_id' => $user_id])->find('level');
        $user_level = $user_info['level'];
        //  会员以下  不处理冻结
        if ($user_level <= 3)
            return false;
        $frozen_list = M('account_log')->where(['user_id' => $user_id, 'type' => 6, 'frozen_money' => ['NEQ', 0]])->select();
        foreach ($frozen_list as $key => $val)
        {
            if ($action  == 'change_time'){
                $log_data['change_time'] = time();
                M('account_log')->where(['log_id' => $val['log_id'], 'type' => 6])->save($log_data);
                continue;
            }
            //  符合提现
            if (($val['change_time'] + 3600 * 24 * 7) < time()){
                //  冻结资金转为余额操作

                //  更新余额  冻结资金
                $data['user_money'] = $user_info['user_money'] + $val['frozen_money'];
                $data['frozen_money'] = $user_info['frozen_money'] - $val['frozen_money'];
                M('users')->where(['user_id' => $user_id])->save($data);

                $log_data['user_money'] = $val['frozen_money'];
                $log_data['frozen_money'] = 0;
                M('account_log')->where(['log_id' => $val['log_id'], 'type' => 6])->save($log_data);
            }
        }
    }

    /**
     * 身份产品分销奖
     * @return $order 订单
     */
    private function identity_prize($order){
        $order_info = Order::with('OrderGoods.goods')->where('order_id',$order['order_id'])->find();

        $goods_list = $order_info->order_goods;

        $this->contact_order = $order_info;

        $pattern    = distributCache('settlement.pattern'); //分佣模式0按商品设置的1按订单实际支付

        $is_freight    = distributCache('settlement.freight'); //是否开启含运费分佣

        $regrade    = $this->config['general_prize_set_level']; //返佣级数1返一级2返二级3返三级
        $distribut_price = 0;//初始化

        if($pattern)
        {
            foreach($goods_list as $k=>$v)
            {
                //判断该产品是否身份产品
                $type = $v['goods']['type_id'];
                $commision = $v['member_goods_price'];//会员折扣价作为商品订单支付价格（后期根据需求改动）

                if(!$type) continue;
                $distribut_price += $commision;
            }
        }else
        {
            foreach($goods_list as $k=>$v)
            {
                //判断该产品是否身份产品
                $type = $v['goods']['type_id'];
                //判断该产品是否有分佣
                $commision = $v['goods']['commission'];

                if(!$type && !$commision) continue;
                $distribut_price += $commision;
            }
        }
        if($is_freight){
            $distribut_price = $distribut_price + $order['shipping_price'];
        }
        $this->distribut_price = $distribut_price;

        $user = Users::get($order['user_id']);

        Db::startTrans();

        try{
            switch($regrade)
            {
                case 1:
                    $this->userDistributDo($user['first_leader'],1,$order,'identity_prize');
                    break;
                case 2:
                    $this->userDistributDo($user['first_leader'],1,$order,'identity_prize');
                    $this->userDistributDo($user['second_leader'],2,$order,'identity_prize');
                    break;
                case 3:
                    $this->userDistributDo($user['first_leader'],1,$order,'identity_prize');
                    $this->userDistributDo($user['second_leader'],2,$order,'identity_prize');
                    $this->userDistributDo($user['third_leader'],3,$order,'identity_prize');
                    break;
            }
            Db::commit();
        }catch(\Exception $e)
        {
            Db::rollback();
        }
    }

    /**
     * 普通产品分销奖
     * @return $order 订单
     */
    public function general_prize($order){

        $order_info = Order::with('OrderGoods.goods')->where('order_id',$order['order_id'])->find();

        $goods_list = $order_info->order_goods;

        $this->contact_order = $order_info;

        $pattern    = distributCache('settlement.pattern'); //分佣模式0按商品设置的1按订单实际支付

        $is_freight    = distributCache('settlement.freight'); //是否开启含运费分佣

        $regrade    = $this->config['general_prize_set_level']; //返佣级数0返一级1返二级2返三级

        $distribut_price = 0;//初始化
        if($pattern)
        {
            foreach($goods_list as $k=>$v)
            {
                //判断该产品是否身份产品
                $type = $v['goods']['type_id'];
                $commision = $v['member_goods_price'];//会员折扣价作为商品订单支付价格（后期根据需求改动）

                if($type) continue;
                $distribut_price += $commision;
            }
            //$distribut_price = $order['order_amount']; //分佣金额
        }else
        {
            foreach($goods_list as $k=>$v)
            {
                //判断该产品是否身份产品
                $type = $v['goods']['type_id'];
                //判断该产品是否有分佣
                $commision = $v['goods']['commission'];

                if($type && !$commision) continue;
                $distribut_price += $commision;
            }
        }
        if($is_freight){
            $distribut_price = $distribut_price + $order['shipping_price'];
        }
        $this->distribut_price = $distribut_price;

        $user = Users::get($order['user_id']);
        Db::startTrans();
        try{
            switch($regrade)
            {
                case 1:
                    $this->userDistributDo($user['first_leader'],1,$order,'general_prize');
                    break;
                case 2:
                    $this->userDistributDo($user['first_leader'],1,$order,'general_prize');
                    $this->userDistributDo($user['second_leader'],2,$order,'general_prize');
                    break;
                case 3:
                    $this->userDistributDo($user['first_leader'],1,$order,'general_prize');
                    $this->userDistributDo($user['second_leader'],2,$order,'general_prize');
                    $this->userDistributDo($user['third_leader'],3,$order,'general_prize');
                    break;
            }
            Db::commit();
        }catch(\Exception $e)
        {
            Db::rollback();
        }
    }
    //用户产生分佣
    private function userDistributDo($user_id,$level,$order,$prize = '')
    {
        //根据每一层的用户做处理
        $user_info = Users::get($user_id);
        $Distribut = new DistributPrize();
        $res = $Distribut->configCache($prize,$user_info['level']);

        if($res && !$res['general_prize_switch']) return ;

        $distribut_price = $this->distribut_price;

        if(empty($user_info) || !$user_info['is_distribut'] || !$distribut_price) return ;
         if ($user_info['level'] == 1) return ;

        switch($level)
        {
            case 1:
                $price = $distribut_price *  $this->config['first_rate'];

                break;
            case 2:
                $price = $distribut_price *  $this->config['second_rate'];
                break;
            case 3:
                $price = $distribut_price *  $this->config['third_rate'];
                break;
        }

        //格式化价格
        $price = sprintf('%.2f',$price/100);

        $buy_user_info = Users::get($order['user_id']);

        //处理分成记录
        $data['user_id'] = $user_id;
        $data['buy_user_id'] = $order['user_id'];
        $data['nickname'] = $buy_user_info->nickname;
        $data['order_sn'] = $order['order_sn'];
        $data['order_id'] = $order['order_id'];
        $data['goods_price'] = $order['total_amount'];
        $data['money'] = $price;
        $data['level'] = $user_info['distribut_level'];
        $data['create_time'] = time();
        $data['status'] = $this->contact_order->pay_status;
        $log = db('rebate_log')->insert($data);

        if(!$log)
            throw new \think\Exception('处理分销记录出错');
    }
    /*
     * 折扣奖
     * @param $goods
     * */
    public  function discount_prize($goods,$key = 'shop_price'){
        if(!$this->config['discount_prize_switch']){
            if($goods['type_id'] == 6 && $goods['status'] == 0){
                return $goods['trade_price'];
            }elseif ($goods['type_id'] == 6 && $goods['status'] == 0){
                return $goods['price'];
            }else{
                return $goods[$key];
            }
        }
        //  查看是否开启复购
        if ($this->config['discount_prize_repeat_buy']){
            // 开启复购
            $state = array('in','1,2,4');
            $order_count = Db::name('order')->where(['user_id' => $this->user_id,'order_status'=>['in',$state]])->count();
            if ($order_count < 1) return $goods[$key];
        }

        switch ($this->config['discount_prize_goods_status']) {
            case 0:  // 只算普通商品
                if ($goods['type_id']) return $goods[$key];
                // 当前商品不是身份产品  按当前折扣
                return $goods[$key] * $this->config['discount'] / 100;
                break;
            case 1: // 只算身份产品
                if ($goods['type_id']) return  $this->config[$key] * $this->config['discount'] / 100;
                return $goods[$key];
                break;
            case 2: //全部
                return $goods[$key] * $this->config['discount'] / 100;
                break;
            default:
                return $goods[$key];
                break;
        }
    }

    /**
     * 管理奖
     * @return [type] [description]
     */
    public function management_prize(){
        //  获取管理奖开关信息
        $mana_levels = Db::name('distribut_system')
            ->field('value,level_id')
            ->where(['inc_type'=> 'management_prize', 'name' => 'management_prize_switch','value' => ['EQ',1]])
            ->column('level_id');
        // 满足管理奖的等级条件
        $mana_id_list = array_unique($mana_levels);

        // 符合等级身份的人
        $mana_users = Db::name('users')
            ->field('user_id,first_leader,second_leader,level')
            ->where(['level'=> ['in',$mana_id_list]])
            ->select();
        $Distribut = new DistributPrize();
        $wechat = $this->wechat;
//        dump($mana_users);die;
        foreach ($mana_users as $key => $val)
        {
            //  获取当前身份等级配置
            $config = $Distribut->configCache('management_prize', $val['level']);

            // 获取本身等级 根据当前设置的等级获取 以及上级等级 上上级等级 上上上级等级
            $oneself_level = $this->getUserLevel($val['user_id']);
            $first_leader_level = $this->getUserLevel($val['first_leader']);
            $seconed_leader_level = $this->getUserLevel($val['second_leader']);

            $condition1 = ($oneself_level === $first_leader_level && $first_leader_level === $seconed_leader_level);
            $condition2 = ($oneself_level === $first_leader_level);
            if ($val['level'] == 3){
                if ($first_leader_level === $seconed_leader_level){
                    $this->managementDo($val, $config, 'third', $wechat);
                    continue;
                }
                $arr = [4,5,6];
                if (in_array($first_leader_level, $arr)){
                    $this->managementDo($val, $config, 'second', $wechat);
                    continue;
                }
            }

            //  三者身份相同
            if ($condition1) {
                $this->managementDo($val, $config, 'third', $wechat);
                continue;
            }

            //  二者身份相同
            if ($condition2) {
                $this->managementDo($val, $config, 'second', $wechat);
            }
        }
    }



    /*
     * 执行管理奖
     * @param $user_id       自身ID
     * @param $config         当前身份等级的奖项配置
     * 获取自身  上级  当日
     * */
    public function managementDo($user, $config,$level,$wechat){
        try {
            $oneself_money = $this->getDayIncome($user['user_id']);  // 获取自身的当天 会员身份产品  收入金额

            $distribut_money = $oneself_money * $config['first_rate'] / 100;

            if ($distribut_money == 0) {return false;}

            // 校验上级身份 是否满足形象代言人的条件
            $action = true;
            $first_leader_level = $this->getUserLevel($user['first_leader']);

            if ($first_leader_level == 4 || $first_leader_level == 5 || $first_leader_level == 6) {
                $qua = new \app\common\logic\QualificationLogic();
                $qua_res = $qua->validate_qualification(3,$user['first_leader'],['condition_recommend']);
                if (!$qua_res) {$action = false;}
            }

            if ($action) {
                $desc = '管理奖获得:'.$distribut_money.'元';
                $wechat->sendTemplateMsgPrize($user['first_leader'], '管理奖', $distribut_money);
                accountLog($user['first_leader'],$distribut_money,0, $desc,0,0,'',8);
            }


            //  设置了二级   三者相同的身份 返s上上级
            if ($config['management_prize_set_level'] == 2 && $level == 'third') {

                $first_leader_money = $oneself_money * $config['second_rate'] / 100;
                if ($distribut_money == 0) {return false;}

                //  校验上级身份 是否满足形象代言人的条件
                $action2 = true;
                $second_leader_level = $this->getUserLevel($user['second_leader']);
                if ($second_leader_level == 4 || $second_leader_level == 5) {
                    $qua = new \app\common\logic\QualificationLogic();
                    $qua_res = $qua->validate_qualification(3,$user['second_leader'],['condition_recommend']);
                    if (!$qua_res) {$action2 = false;}
                }
                if ($action2) {
                    $desc = '管理奖获得:'.$first_leader_money.'元';
                    $wechat->sendTemplateMsgPrize($user['second_leader'], '管理奖', $first_leader_money);
                    accountLog($user['second_leader'],$first_leader_money,0, $desc,0,0,'',8);
                }

            }

        }catch(\Exception $e){
            throw new \think\Exception('处理分销记录出错');
        }
    }

    /*
     * 获取用户 当天的收入
     * @param $user_id 用户ID
     * */
    public function getDayIncome($user_id){
        // 获取当前用户下所有的会员
        $user_id_list = Db::name('users')->where(['first_leader' => $user_id, 'level' => 2])->column('user_id');

        // 时间条件
        $time = time();
        $time_start = strtotime(date('Y-m-d', $time).' 00.00.00');
        $time_end = strtotime(date('Y-m-d', $time).' 23.59.59');

        // 查询 会员身份产品ID
        $res = Db::name('distribut_system')->field('value')->where(['inc_type' => 'condition_buy', 'name'=> 'is_buy_appoint_product_enable', 'level_id' => 2])->column('value');
        $type_id = [];
        foreach ($res as $k => $v) {
            $arr2 = explode(',',$v);
            foreach ($arr2 as $key => $value) {
                $type_id[] = $value;
            }
        }
        $type_id = array_unique($type_id);
        sort($type_id);
        // 去除为type_id 为 0  0 普通产品
        if ($type_id[0] == 0) {
            unset($type_id[0]);
        }
        // 获取总佣金
        $distribut_money = Db::name('order')
            ->alias('o')
            ->field('o.*')
            ->join('__ORDER_GOODS__ og', 'o.order_id = og.order_id', 'LEFT')
            ->join('__GOODS__ g', 'og.goods_id = g.goods_id', 'LEFT')
            ->where([
                'o.user_id' =>['in', $user_id_list],
                'o.pay_time'=>['between', [$time_start, $time_end]],
                'o.pay_status' => 1,
                'g.type_id' =>['in', $type_id],
            ])
            ->sum('o.order_amount');
        if ($distribut_money) {
            return $distribut_money;
        } else {
            return 0;
        }
    }

    /*
     * 获取用户 身份等级
     * @param $user_id 用户ID
     * */
    public function getUserLevel($user_id){
        return Db::name('users')->where(['user_id' => $user_id])->value('level');
    }

    /*
     * 区域奖
     * @param
     * */
    public function region_prize(){
        // 获取已开启的身份等级
        $region_levels = Db::name('distribut_system')
            ->field('value,level_id')
            ->where(['inc_type'=> 'areas_share_prize', 'name' => 'areas_share_prize_switch','value' => ['EQ',1]])
            ->select();
        // 满足管理奖的等级条件
        foreach($region_levels as $key => $val)
        {
            $region_prize_info[$val['level_id']] = $val['value'];
        }
        $region_levels_id = array_keys($region_prize_info);
        $region_id_list = array_unique($region_levels_id);


        // 符合等级身份的人  合伙人
        $region_users = Db::name('users')
            ->field('user_id,first_leader,second_leader,level')
            ->where(['level'=> ['in',$region_id_list],'first_leader'=>['GT',0],'second_leader'=>['GT',0]])
            ->select();
        foreach ($region_users as $key => $val)
        {
            if ($val['level'] == 6) {
                $this->getUserPath($val['user_id']);
            } else  {

            }
        }

    }

    /*
     * 区域分红  市代理
     * */
    public function region_city_prize(){
        // 校验开关
        $region_switch = Db::name('distribut_system')
            ->field('value,level_id')
            ->where(['inc_type'=> 'areas_share_prize', 'name' => 'areas_share_prize_switch','value' => ['EQ',1], 'level_id' => 5])
            ->find();
        if (!$region_switch)
            return false;

        // 查询奖项 折算比例
        $money_unit_config = Db::name('distribut_system')
            ->field('name,value')
            ->where(['inc_type'=> 'areas_share_prize', 'name' => 'areas_share_prize', 'level_id' => 5])
            ->find();

        //  寻找市代理
        $city_users = Db::name('users')
            ->field('user_id,first_leader,second_leader,level,region_code')
            ->where(['level'=> 5])
            ->select();
        $wechat = $this->wechat;
        foreach ($city_users as $key => $val)
        {
            if ($val['region_code'] == 0){continue;}
            $city_list = explode(',',$val['region_code']);
            //  这个月的业绩
            $orderMoney = $this->getTypeOrderMoney($city_list);

            // 查 上一个月的业绩
            $last_partner_prize = Db::name('report')->where(['user_id' => $val['user_id']])->find();
            $this->regionPrizeDo($val,$orderMoney,$last_partner_prize,$money_unit_config, $wechat);
        }
    }

    /*
     * 区域分红  合伙人
     * */
    public function region_partner_prize(){
        // 校验开关
        $region_switch = Db::name('distribut_system')
            ->field('value,level_id')
            ->where(['inc_type'=> 'areas_share_prize', 'name' => 'areas_share_prize_switch','value' => ['EQ',1], 'level_id' => 6])
            ->find();
        if (!$region_switch)
            return false;

        // 查询奖项 折算比例
        $money_unit_config = Db::name('distribut_system')
            ->field('name,value')
            ->where(['inc_type'=> 'areas_share_prize', 'name' => 'areas_share_prize', 'level_id' => 6])
            ->find();
        //  寻找合伙人
        $partner_users = Db::name('users')
            ->field('user_id,first_leader,second_leader,level')
            ->where(['level'=> 6])
            ->select();
        $wechat = $this->wechat;
        foreach ($partner_users as $key => $val)
        {
            $this->getUserPath($val['user_id']);
            if (!$this->user_id_list){continue;}

            //  这个月的业绩
            $orderMoney = $this->getTypeOrderMoney();

            // 查 上一个月的业绩
            $last_partner_prize = Db::name('report')->where(['user_id' => $val['user_id']])->find();

            //  第一个月成为合伙人  尚且下级没有用户购买身份产品
            if (!$last_partner_prize && !$orderMoney){continue;}

            $this->regionPrizeDo($val, $orderMoney, $last_partner_prize, $money_unit_config,$wechat);

        }
    }

    /*
     * 处理分销奖
     * */
    public function regionPrizeDo($userInfo, $orderMoney, $last_partner_prize, $config, $wechat){
        $clear_time =  Db::name('distribut_system')
            ->field('name,value')
            ->where(['inc_type'=> 'areas_share_prize', 'name' => 'areas_share_prize_clear_time', 'level_id' => $userInfo['level']])
            ->find();
        Db::startTrans();

        try {
            // 第一个月  表中无数据
            if (!$last_partner_prize && $orderMoney){
                $distribut_money = round($orderMoney * $config['value']  / 100, 2);

                $report_data['user_id'] = $userInfo['user_id'];
                $report_data['performance'] = $orderMoney;
                $report_data['month'] = 1;
                $report_data['add_time'] = time();
                $desc = '区域奖获得:'.$distribut_money.'元';
                $wechat->sendTemplateMsgPrize($userInfo['user_id'], '区域奖', $distribut_money);
                $res = accountLog($userInfo['user_id'],$distribut_money,0, $desc,0,0,'',9);
                $res = Db::name('report')->insertGetId($report_data);

            } else {

                $distribut_money = round(($orderMoney + $last_partner_prize['performance']) * $config['value'] / 100, 2);

                if ($last_partner_prize['month'] + 1 >= $clear_time['value']) {
                    $month = 0;
                    $performance = 0;
                } else {
                    $month = $last_partner_prize['month'] + 1;
                    $performance = $orderMoney + $last_partner_prize['performance'];
                }

                $report_data['user_id'] = $userInfo['user_id'];
                $report_data['performance'] = $performance;
                $report_data['month'] = $month;
                $report_data['add_time'] = time();
                $desc = '区域奖获得:'.$distribut_money.'元';
                $wechat->sendTemplateMsgPrize($userInfo['user_id'], '区域奖', $distribut_money);
                accountLog($userInfo['user_id'],$distribut_money,0, $desc,0,0,'',9);
                $res = Db::name('report')->where(['id' => $last_partner_prize['id']])->save($report_data);
            }
            if ($res){
                $this->user_id_list = [];
                Db::commit();
            }
        }
        catch(\Exception $e)
        {
            Db::rollback();
        }
    }

    /*
     * 车房奖
     * @param $money 转账的金额
     * */
    public function integral_prize($money){
        // 奖项名字与方法不一致 重新获取奖项  lzz
        $this->setConfigCache('car_home_prize');
        // 房车奖开启
        if ($this->config['car_home_prize_switch']) {
            try {
                // 当前提现金额不满足 该奖项的金额设置
                if ($money < $this->config['car_home_money']) {
                    return false;
                } else {
                    $integral = $money * $this->config['car_home_integral'] / 100;
                    $wechat = $this->wechat;
                    $wechat->sendTemplateMsgPrize($this->user_id, '车房奖', $integral);
                    accountLog(
                        $this->user_id,
                        0,
                        $integral,
                        '车房奖获得：'.$integral.'积分',
                        0,
                        0,
                        0,
                        2
                    );
                    return $money - $integral;
                }
            } catch(\Exception $e){
                $this->logObj->log_result($this->log_name, "【用户参数异常】:\n" . $this->user_id . "\n");
            }

        }
    }

    /*
     * 获取是否满足车房奖信息
     * */
    public function getIntegralPrizeInfo($money){
        $this->setConfigCache('car_home_prize');
        if (!$this->config['car_home_prize_switch'])
        {
            $data['is_prize'] = '未开启';
            $data['integral'] = 0;
            return $data;
        }
        $data = [];
        if ($money < $this->config['car_home_money']) {
            $data['is_prize'] = '不符合';
            $data['integral'] = 0;
        } else {
            $data['is_prize'] = '符合';
            $data['integral'] = $money * $this->config['car_home_integral'] / 100;
        }

        return $data;
    }

    /*
     * 市场补助奖结算奖池
     * @param
     * */
    public function market_settle_prize(){
        $pagenum = 5;
        $share_price = Db::name('distribut_system')
            ->where(['inc_type' => 'settlement', 'name' => 'share_price'])
            ->find();//会员分红额

        $count =  M('users')->where(['level'=>['GT',1],'total_jackpot'=>['LT',$share_price['value']]])->count();
        $pages = ceil($count / $pagenum);
        if(1 <= $pages){
            $funds = Db::name('distribut_system')
                ->where(['inc_type' => 'settlement', 'name' => 'funds'])
                ->find();//奖池奖金
            $funds = $funds['value'];
            $share_price = Db::name('distribut_system')
                ->where(['inc_type' => 'settlement', 'name' => 'share_price'])
                ->find();//会员分红额
            $share_price = $share_price['value'];
            $round = floor($funds / $count);
            if(1 < $round){
                $user_logic = new UsersLogic();
                $total = 0;
                $i=1;
                $wechat = $this->wechat;
                while($i<=$pages) {
                    $page =  $pagenum*($i - 1);
                    $users =  M('users')->where(['level'=>['GT',1],'total_jackpot'=>['LT',$share_price]])->limit($page,$pagenum)->select();

                    foreach($users as $log)
                    {
                        $num = ($share_price - $log['total_jackpot']);
                        if($round < $num){
                            $total = $total ? $total - $round : $funds - $round;//更新奖池基金
                            $rebate_log = ['desc'=>'补助奖分红获得余额'.$round.'元','type'=>3];

                            $wechat->sendTemplateMsgPrize($log['user_id'], '补助奖', $round);

                            $user_logic->setAccountOrJackpot($log['user_id'],'account',$round,$rebate_log);
                            //更新用户所累积的分佣金额
                            $user = Users::get($log['user_id']);
                            $user->total_jackpot += $round;
                            $user->save();
                        }else{
                            $total = $total ? $total - ($round - $num) : $funds - ($round - $num);//更新奖池基金
                            $rebate_log = ['desc'=>'补助奖分红获得余额'.$num.'元','type'=>3];

                            $wechat->sendTemplateMsgPrize($log['user_id'], '补助奖', $num);

                            $user_logic->setAccountOrJackpot($log['user_id'],'account',$num,$rebate_log);
                            //更新用户所累积的分佣金额
                            $user = Users::get($log['user_id']);
                            $user->total_jackpot += $num;
                            $user->save();
                        }

                    }
                    $i++;
                }
                $funds_res = Db::name('distribut_system')
                    ->where(['inc_type' => 'settlement', 'name' => 'funds'])
                    ->save(['value'=>$total]);
                $this->market_settle_prize();
            }
        }
    }

    /*
     * 递归查询符合条件
     *  //  身份是体验店  市代理 需要验证 形象代言人条件
     * */
    public function recursive($id, $level_arr,$limit_level){
        if (!$id && $level_arr) {
            return ['code' =>-2, 'msg' => '请传参数'];
        }
        $userInfo = Db::name('users')->field('user_id,first_leader,level')->where(['user_id' => $id])->find();
        if (!$userInfo)  return false;
        if (in_array($userInfo['level'],$level_arr)){
            //  验证
            if  (in_array($userInfo['level'], $limit_level)){
                $qua = new \app\common\logic\QualificationLogic();
                $qua_res = $qua->validate_qualification(3,$userInfo['user_id'],['CATE_RECOMMEND']);
                if (!$qua_res) {
                    return $this->recursive($userInfo['first_leader'],$level_arr,$limit_level);
                }
            }
            return $userInfo;
        } else {
            return $this->recursive($userInfo['first_leader'],$level_arr,$limit_level);
        }
    }

    public function getUserPath($user_id, $level_rules = 6){
        $field = 'user_id,first_leader,level';
        // 获取本身的上级信息

        $userInfo = Db::name('users')
            ->field($field)
            ->where(['user_id' => $user_id])
            ->find();

        //  不存在的用户   断开
        if (!$userInfo)  return false;

        // 获取下级信息
        $user_path_list = Db::name('users')
            ->field($field)
            ->where(['first_leader' => $user_id])
            ->select();
        // 不存在下级信息
        if (!$user_path_list) return false;
        foreach ($user_path_list as $key => $val)
        {
            // 当前下级 如果为 当前身份为合伙人的  跳开   不是  收集起来
            if ($val['level'] == $level_rules){
                continue;
            }else {
                $this->user_id_list[] = $val['user_id'];
            }
            //  递归获取当前下级 的  下级
            $this->getUserPath($val['user_id']);
        }
    }

    /*
     *  获取当月身份订单 订单金额
     * */
    public function getTypeOrderMoney($city_id = 0){

        //   查询当月 所有 身份产品订单
        $first_day =date('Y-m-01', strtotime(date("Y-m-d")));
        $first_time =strtotime(date('Y-m-01', strtotime(date("Y-m-d"))).' 00.00.00');

        $field = 'o.order_id,o.order_amount,o.user_id,g.type_id';
        $last_time =strtotime(date('Y-m-d', strtotime("$first_day +1 month -1 day")).' 23.59.59');
        $type_id = Db::name('distribut_system')->where(['level_id' => 4, 'name' => 'is_buy_appoint_product_enable'])->value('value');
        if ($city_id){
            $where = [
                'o.city' => ['in', $city_id],
                'o.add_time' => ['between', "$first_time,$last_time"],
                'g.type_id' => $type_id,
            ];
        } else {
            $user_list = $this->user_id_list;
            if (!$user_list) return 0;
            $where =  [
                'o.user_id' =>['in', $user_list],
                'o.add_time' => ['between', "$first_time,$last_time"],
                'g.type_id' => $type_id,
            ];
        }

        $order_list = Db::name('order')
            ->alias('o')
            ->field($field)
            ->join('__ORDER_GOODS__ og', 'o.order_id = og.order_id', 'LEFT')
            ->join('__GOODS__ g', 'og.goods_id = g.goods_id', 'LEFT')
            ->where($where)
            ->select();

        $money_account_list = array_column($order_list, 'order_amount');
        return array_sum($money_account_list);
    }

    /**
     * 获取自身身份产品订单编号以及订单ID
     * @return [type] [description]
     */
    public function getOnselfTypeOrder($user_id){
        return Db::name('order')->alias('o')
            ->field('o.order_id,o.order_sn')
            ->join('__ORDER_GOODS__ og', 'o.order_id = og.order_id', 'LEFT')
            ->join('__GOODS__ g', 'og.goods_id = g.goods_id', 'LEFT')
            ->where([
                'user_id' => $this->user_id,
                'g.type_id' =>['GT', 0]
            ])
            ->order('o.order_id desc')
            ->find();
    }

}