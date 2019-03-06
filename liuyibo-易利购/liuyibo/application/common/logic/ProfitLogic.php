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
     public $achievement = 0; //直推消费商以及所有下级业绩
    /**
    * 收益返点
    */
    public function profit()
    {
        
        
        //配置信息
        $system = tpCache('ylg_spstem_role');
//       $users = Db::name('users')->where('level > 0')->select();
        $users = Db::name('users')->where(['level'=>['in','2,4']])->select();
       foreach($users as $k=>$v){
            if($v['level'] == 2){
                //消费商收益
                $this->consumerprofit($v,$system);
            }elseif($v['level'] == 4){
                //合伙人收益
                $this->partnerprofit($v,$system);
            }
       }
       //当月业绩清0；赋值上月业绩
        Db::query("UPDATE tp_users  SET sales = monthly_performance ,monthly_performance=0");
       $this->zero_clearing();
    }

    /**
     * 消费商收益返点 直推层每月累计（）元业绩返（）%
     */
    public function consumerprofit($user,$system)
    {
        //直推层每月累计业绩
        $monthly_performance = Db::name('users')->where('first_leader',$user['user_id'])->sum('monthly_performance');
        if(empty($monthly_performance)||$monthly_performance < $system['consumer']){
            return true;
        }else{
            $money = $monthly_performance*$system['consumer2'];
            if($money>0){
                //消费商收益返点
                Db::name('users')->where('user_id',$user['user_id'])->inc('user_money',$money*(1-$system['personal_income']))->inc('rebate_revenue',$money*(1-$system['personal_income']))->update();
                balancelog($user['user_id'],$user['user_id'],$money,4,$user['user_money'],$user['user_money']+$money);

                //个人所得税
                balancelog($user['user_id'],$user['user_id'],-($money*$system['personal_income']),19,$user['user_money']+$money,$user['user_money']+$money-($money*$system['personal_income']));
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
     * 合伙人收益返点
     */
    public function partnerprofit($user,$system)
    {
        //直推代理商收入
        $total = Db::name('users')->where(['first_leader'=>$user['user_id'],'level'=>3])->field('sum(total_amount) as total_amount,sum(rebate_revenue) as rebate_revenue')->find();
        if(empty($total['total_amount'])){
            $total['total_amount']=0;
        }
        if(empty($total['rebate_revenue'])){
            $total['rebate_revenue']=0;
        }
        $total_amount=$total['total_amount']+$total['rebate_revenue'];

        $money1 = $total_amount*$system['agent_partner'];
        //直推消费商以及所有下级的业绩补贴
        $rebate_revenue = Db::name('users')->where(['first_leader'=>$user['user_id'],'level'=>['in','1,2']])->sum('monthly_performance');
        $money2 = $rebate_revenue*$system['agent_partner2'];
        $amount=$money1+$money2;
        if($amount>0){
            //合伙人收益返点
            Db::name('users')->where('user_id',$user['user_id'])->inc('user_money',$amount*(1-$system['personal_income']))->update();
            balancelog($user['user_id'],$user['user_id'],$amount,12,$user['user_money'],$user['user_money']+$amount);

            //个人所得税
            balancelog($user['user_id'],$user['user_id'],-($amount*$system['personal_income']),19,$user['user_money']+$amount,$user['user_money']+$amount-($amount*$system['personal_income']));
            return true;
        }else{
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
            'total_amount'=>0,
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
                    $store_count=Db::name('goods')->where("goods_id = {$value['goods_id']}")->value('store_count');
                    Db::name('goods')->where("goods_id = {$value['goods_id']}")->update(['store_count'=>$value['snum']]);
                    digitalassets_log(1,1,$value['goods_id'],$value['snum'],'专区转换库存变更',$store_count,$value['snum'],2);
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