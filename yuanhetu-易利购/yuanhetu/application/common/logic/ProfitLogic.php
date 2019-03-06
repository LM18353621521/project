<?php

namespace app\common\logic;

use think\Model;
use think\Db;

/**
 * 收益逻辑
 */
class ProfitLogic extends model
{

//     public $rebate = 0; //所有下级返点
     //public $achievement = 0; //直推消费商以及所有下级业绩
    /**
    * 平台加权分红 日返 奖项二，奖项四
    */
    public function profit()
    {
        //配置信息
        $system = tpCache('ylg_spstem_role');
        //获取分红高级会员和股东
        //$users = Db::name('users')->where(['level'=>['in','3,4']])->where('dividend_time','<> time',date('Y-m-d'))->select();
        $users = Db::name('users')->where(['level'=>['in','3,4']])->select();
        $count=0;
        //去除不符合条件的高级会员
        foreach ($users as $key => $value) {
            if($value['level'] == 3){
                $res=Db::name('users')->where(['first_leader'=>['in',$value['user_id']],'level'=>['in','1,2,3,4'],'monthly_performance'=>['>=',$system['consumption']]])->select();
                if(count($res)>=$system['user_num']){
                    continue;
                }else{
                    unset($users[$key]);
                }
            }else{
                //统计股东人数
                $count++;
            }
        }
        //当日产品净利
        //$net_income = Db::name('order')->whereTime('pay_time', 'yesterday')->where(['pay_status'=>1,'type'=>1])->sum('net_income');
        $net_income = Db::name('order')->whereTime('pay_time', 'today')->where(['pay_status'=>1,'type'=>1])->sum('net_income');

        if($net_income>0&&count($users)>0){
            //奖项二
            $num1=($net_income*$system['agent_rebate'])/count($users);
            $data['type']=14;
            $data['user_id']=0;
            $data['user_money']=$net_income;//总净利
            $data['pay_points']=count($users);//总人数
            $data['frozen_money']=$num1;//均分值
            $data['change_time']=time();
            $data['desc']="奖项二";
            M('account_log')->add($data);
        }
        if($net_income>0&&$count>0){
           //奖项四
            $num2=($net_income*$system['reserve_funds2'])/$count;
            $data2['type']=15;
            $data2['user_id']=0;
            $data2['user_money']=$net_income;//总净利
            $data2['pay_points']=$count;//总人数
            $data2['frozen_money']=$num2;//均分值
            $data2['change_time']=time();
            $data2['desc']="奖项四";
            M('account_log')->add($data2);
        }

        if($net_income>0&&count($users)>0){
            foreach($users as $k=>$v){
                if($v['level'] == 3){
                    //高级会员奖项二
                    $this->consumerprofit($v,$num1);
                }elseif($v['level'] == 4){
                    //股东奖项二，奖项四
                    $this->partnerprofit($v,$num1,$num2);
                }
           }
        }
    }

    /**
     * 高级会员奖项二
     */
    public function consumerprofit($user,$num)
    {

        if(empty($user)||empty($num)){
            return true;
        }else{
            if($num>0){
                //高级会员奖项二
                $data=[
                    'dividend_time'=>date('Y-m-d'),
                    'user_money'=>['exp','user_money+'.$num]
                ];
                Db::name('users')->where('user_id',$user['user_id'])->update($data);
                balancelog($user['user_id'],$user['user_id'],$num,20,$user['user_money'],$user['user_money']+$num);
            }
            return true;
        }
    }

    /**
     * 返代理商无限层下级业绩补贴
     */
    public function agentprofit($user,$system)
    {
        $this->saleprofit($user,$system);

        // 判断所有下级业绩是否大于0
        if($user['performance'] > 0){
            $this->subordinate($user,$system);
        }
        return true;

    }
    /**
     * 股东奖项二，奖项四
     */
    public function partnerprofit($user,$num1,$num2)
    {
        $num=$num1+$num2;
        if(empty($user)||empty($num)){
            return true;
        }else{
            if($num>0){
                $data=[
                    'dividend_time'=>date('Y-m-d'),
                    'user_money'=>['exp','user_money+'.$num]
                ];
                //股东
                Db::name('users')->where('user_id',$user['user_id'])->update($data);
                //奖项二
                balancelog($user['user_id'],$user['user_id'],$num1,20,$user['user_money'],$user['user_money']+$num1);
                //奖项四
                balancelog($user['user_id'],$user['user_id'],$num2,22,$user['user_money']+$num1,$user['user_money']+$num);
            }
            return true;
        }
    }

    /**
     * 销售额收益
     */
    public function saleprofit($user,$system)
    {
        //获取销售额配置信息
       $arr  = unserialize($system['pushs']);
       $salenum  = $user['performance']+$user['monthly_performance'];
        //判断
        for($i = count($arr)-1;$i>0;$i--){
            if($salenum >= $arr[$i]['sales']){
                $money = $salenum*$arr[$i]['rebate'];
                break;
            }
        }
        if(empty($money)){
            return true;
        }else{
            if(Db::name('users')->where('user_id',$user['user_id'])->setInc('user_money',$money)){
                //余额日志
                balancelog($user['user_id'],$user['user_id'],$money,7,$user['user_money'],$user['user_money']+$money);
            }
        }
        //生产日志记录
    }
    /**
     * 下级业绩补贴
     */
    public function subordinate($user,$system)
    {
        //所有直推下级消费商
        $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND level = 1 AND monthly_performance > 0")->select();

        if(empty($users)){
            return true;
        }else{
            $strid = '';
            for ($i=0;$i<count($users);$i++){
                // 当月业绩*补贴 = 返点
                $money = $users[$i]['monthly_performance']*$system['agent_rebate'];
                //增加收益积分和返点收入
                if(Db::name('users')->where('user_id',$users[$i]['user_id'])->inc('user_money',$money)->inc('rebate_revenue',$money)->update()){
                    $strid .=",".$users[$i]['user_id'];
                    //余额日志
                    balancelog($user['user_id'],$users[$i]['user_id'],$money,5,$users[$i]['user_money'],$users[$i]['user_money']+$money);
                }
            }
            $user['user_id'] = trim($strid,",");
            $this->subordinate($user,$system);
        }
    }
    /**
     * 获取直推代理商收入
     */
    public function agent($user,$system,$id,$money = 0)
    {
        //获取下级返点收入大于0 的用户
        $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND rebate_revenue > 0")->select();
        if(empty($users) && empty($money)){
            return true;
        }elseif(empty($users) && $money > 0){

//             直推代理商收入
               $money  = $money*$system['agent_partner'];
               $user= Db::name('users')->where('user_id',$id)->find();
                if(Db::name('users')->where('user_id',$id)->inc('user_money',$money)->update()){
                    //余额日志
                    balancelog($id,$id,$money,6,$user['user_money'],$user['user_money']+$money);
                }
                return true;
        }else{
            $strid = '';
            for ($i=0;$i<count($users);$i++){
                // 累计返点收入
                $money += $users[$i]['rebate_revenue'];
                $strid.=",".$users[$i]['user_id'];
            }
            $user['user_id'] = trim($strid,',');
            $this->agent($user,$system,$id,$money);
        }
    }

    /**
     * 返消费商以及所有下级的业绩补贴
     */
    public function consumer($user,$system,$num = 1)
    {
        //所有直推下级消费商
        if($num = 1){
            $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND level = 1 AND monthly_performance > 0")->select();
        }else{
            $users = Db::name('users')->where("first_leader in({$user['user_id']}) AND monthly_performance > 0")->select();
        }

        if(empty($users)){
            return true;
        }else{
            $strid = '';
            for ($i=0;$i<count($users);$i++){
                // 当月业绩*补贴 = 返点
                $money = $users[$i]['monthly_performance']*$system['agent_partner'];
                //增加收益积分和返点收入
                if(Db::name('users')->where('user_id',$users[$i]['user_id'])->inc('user_money',$money)->inc('rebate_revenue',$money)->update()){
                    $strid.=",".$users[$i]['user_id'];
                    //余额日志
                    balancelog($user['user_id'],$users[$i]['user_id'],$money,12,$users[$i]['user_money'],$users[$i]['user_money']+$money);
                }
            }
            $user['user_id'] = trim($strid,',');
            $this->consumer($user,$system,$num+1);
        }
    }

    //清零
    public function zero_clearing()
    {
        $arr = array(
            'rebate_revenue'=>0,
            'total_jackpot'=>0,
            'monthly_performance'=>0,
            'performance'=>0
        );
        Db::name('users')->where("level > 0")->update($arr);
    }

    //时间到  商品自动自提  出售列表自动返回代理余额
    public function end_time()
    {
        $mealid = Db::name('goods')->where("type_id = 6 AND is_on_sale = 1 AND status = 0 AND UNIX_TIMESTAMP(agentend_time) < UNIX_TIMESTAMP(NOW())")->column('goods_id');
        if(!empty($mealid)){
            $mealid = implode(',',$mealid);
            //        $goodslist = Db::name('order')->alias('o')
            //            ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$goods_id})")->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
            //            ->group('o.user_id')->select();

            $meallist = Db::name('order')->alias('o')
                ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$mealid})")->field('g.goods_id,g.order_id,g.quota,g.goods_price,user_id,setmeal_id,sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
                ->group('o.user_id,g.setmeal_id')->select();

            foreach($meallist as $k => $v){
                //商品总数量 - 出售数量 - 提货数量 = 剩余数量  自动提货
                $num = $v['nums']-$v['sel']-$v['self'];
                if($num>0){
                    if(!$this->agent_order($v,$num)){
                        echo '提货有误';
                    }
                }
            }
             $result = Db::name('goods')->where("status = 0 AND type_id = 6 AND UNIX_TIMESTAMP(agentend_time) <= UNIX_TIMESTAMP(NOW())")->update(['status'=>1]);
            if($result){
                $data = Db::name('goods_consignment')->field('goods_id,sum(num) snum')->where("goods_id in ($mealid)")->group('goods_id')->select();
                foreach ($data as $key => $value) {
                    Db::name('goods')->where("goods_id = {$value['goods_id']}")->update(['store_count'=>$value['snum']]);
                }
            }
           
        }

        $goods = Db::name('goods')->where("type_id = 6 AND is_on_sale = 1 AND status =1 AND UNIX_TIMESTAMP(saleend_time) < UNIX_TIMESTAMP(NOW())")->column('goods_id');
        if(!empty($goods)){
            $goods_id = implode(',',$goods);
        //        $goodslist = Db::name('order')->alias('o')
        //            ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$goods_id})")->field('sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
        //            ->group('o.user_id')->select();

            $goodslist = Db::name('order')->alias('o')
                ->join('order_goods g','g.order_id = o.order_id')->where("type = 0 AND goods_id in({$goods_id})")->field('g.goods_id,g.order_id,g.quota,g.goods_price,user_id,setmeal_id,sum(goods_num) nums,sum(sell) sel,sum(self_mention) self')
                ->group('o.user_id,g.setmeal_id')->select();

            foreach($goodslist as $k => $v){
            //商品总数量 - 出售数量 - 提货数量 = 剩余数量  自动提货
//                $num = $v['nums']-$v['sel']-$v['self'];
//                if($num>0){
//                    if(!$this->agent_order($v,$num)){
//                        echo '提货有误';
//                    }
//                }
                if(!$this->recovery($v)){
                    echo '回收有误';
                }
             }
            Db::name('goods')->where("goods_id in($goods_id)")->update(['is_on_sale'=>0]);
        }

    }

    //提货订单生成
    public function agent_order($data,$num)
    {
        $address = Db::name('user_address')->where("user_id = {$data['user_id']} AND is_default = 1")->find();
        $orderLogic = new OrderLogic();
        $arr = array(
            'user_id' => $data['user_id'],
            'self_mention_sn' => $orderLogic->get_order_sn(),
            'setmeal_id' => $data['setmeal_id'],
            'goods_id' => $data['goods_id'],
            'order_id' => $data['order_id'],
            'num' => $num,
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'consignee' => $address['consignee'], // 收货人
            'province' => $address['province'],//'省份id',
            'city' => $address['city'],//'城市id',
            'district' => $address['district'],//'县',
            'twon' => $address['twon'],// '街道',
            'address' => $address['address'],//'详细地址',
            'mobile' => $address['mobile'],//'手机'
        );
//        Db::startTrans();
        if (Db::name('self_mention_order')->insert($arr)) {
            if (Db::name('order_goods')->where("order_id = {$data['order_id']}")->setInc('self_mention', $num)) {
                return true;
//                Db::commit();
            }else{
                return false;
//                Db::rollback();
            }
        }else{
            return false;
//            Db::rollback();
        }
    }

    //回收没有出售完的商品
    public function recovery($data)
    {
        $list = Db::name('goods_consignment')->where("user_id = {$data['user_id']} AND setmeal_id = {$data['setmeal_id']}")->field(' sum(surplus_num) num')->find();
        if(!empty($list) && $list['num'] >0){
            Db::startTrans();
            $user = Db::name('users')->where("user_id = {$data['user_id']}")->find();
            $result = Db::name('users')->where("user_id = {$data['user_id']}")->inc('user_money',$data['goods_price']*$list['num'])->inc('frozen_money',$data['quota']*$list['num'])->update();
            $results = Db::name('goods_consignment')->where("user_id = {$data['user_id']} AND setmeal_id = {$data['setmeal_id']}")->update(['surplus_num'=>0]);
            if($result && $results){
                balancelog($data['user_id'],$data['user_id'],$data['goods_price']*$list['num'],14,$user['user_money'],$user['user_money']+$data['goods_price']*$list['num']);
                integrallog($data['user_id'],$data['user_id'],$data['quota']*$list['num'],10,$user['frozen_money'],$user['frozen_money']+$data['quota']*$list['num']);
                Db::commit();
                return true;
            }else{
                Db::rollback();
                return false;
            }
        }
        return true;
    }
}