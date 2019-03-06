<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/21
 * Time: 16:41
 */
namespace app\mobile\controller;
use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;
use think\Controller;
use think\Session;

    class Special extends Controller {
        /**
         * 季度奖结算
         */

        public function quarter_count(){
            quarter_count();
        }
        public function zero_quarter(){
            $res_jsckpot =M('jackpot')->where(array('id'=>1))->update(array('start_time'=>time(),'vip2_twolayers_prize'=>0,'vip3_twolayers_prize'=>0,'vip4_twolayers_prize'=>0,'quarter'=>1));
            $res_jsckpot =M('jackpot')->where(array('id'=>array('gt',1)))->delete();
        }

        public function quarter_countxx(){
            quarter_count();
            die();


            //奖池
            $jackpot = M('jackpot')->find();
//        dump($jackpot);

            //分奖时机
            $can_distribution = tpCache('distribut.can_distribution');
//        echo $can_distribution;

            //时间节点
            $time_node = $can_distribution==1?"pay_time":"confirm_time";

            //当前时间
            $end_time = time();

            //查找符合的订单
        $where['is_jackpot_prize']=0;
            if($can_distribution==1){
                $where['order_status']=array('in',array(0,1,2,4));
                $where['pay_time']=array('lt',$end_time);
            }else{
                $where['order_status']=array('in',array(2,4));
                $where['confirm_time']=array('lt',$end_time);
            }
            $where['a.qrid']=0;

            //总销售额
            $sales_volume_total  = M('order')->alias('a')
                ->join('order_goods og','a.order_id=og.order_id')
                ->where($where)
                ->sum('og.sales_volume');

            $sales_reward = array(
                'start_time'=>$jackpot['start_time'],
                'end_time'=>$end_time,
                'jackpot' =>$sales_volume_total,
                'quarter' =>$jackpot['quarter'],
            );

            //vip1 人数
            $where_u['level'] = 3;
            $vip1_list = M('users')->where($where_u)->field('user_id,level,user_money')->select();
            $where_u['level'] = 4;
            $vip2_list = M('users')->where($where_u)->field('user_id,level,user_money')->select();
            $where_u['level'] = 5;
            $vip3_list = M('users')->where($where_u)->field('user_id,level,user_money')->select();
            $where_u['level'] = 6;
            $vip4_list = M('users')->where($where_u)->field('user_id,level,user_money')->select();


            //销售额分红奖比列
            $sales_reward=distributCache("sales_reward");
            //vip1
            $sales_reward_3 = round($sales_volume_total*$sales_reward['sales_reward_3']/100,2); //可瓜分金额
            $res_sales_reward_3 =quarte_prize("sales_reward_3",$sales_reward['start_time'],$end_time,$sales_volume_total,$sales_reward_3,$sales_reward['sales_reward_3'],$jackpot['quarter'],$vip1_list,0,3,'销售额分红奖');
            //vip2
            $sales_reward_4 = round($sales_volume_total*$sales_reward['sales_reward_4']/100,2);
            $res_sales_reward_4 =quarte_prize("sales_reward_4",$sales_reward['start_time'],$end_time,$sales_volume_total,$sales_reward_4,$sales_reward['sales_reward_4'],$jackpot['quarter'],$vip2_list,0,3,'销售额分红奖');
            //vip3
            $sales_reward_5 = round($sales_volume_total*$sales_reward['sales_reward_5']/100,2);
            $res_sales_reward_5 =quarte_prize("sales_reward_5",$sales_reward['start_time'],$end_time,$sales_volume_total,$sales_reward_5,$sales_reward['sales_reward_5'],$jackpot['quarter'],$vip3_list,0,3,'销售额分红奖');
            //vip4
            $sales_reward_6 = round($sales_volume_total*$sales_reward['sales_reward_6']/100,2);
            $res_sales_reward_6 =quarte_prize("sales_reward_6",$sales_reward['start_time'],$end_time,$sales_volume_total,$sales_reward_6,$sales_reward['sales_reward_6'],$jackpot['quarter'],$vip4_list,0,3,'销售额分红奖');


            //二层外分红奖比列
            $twolayers_reward=distributCache("twolayers_reward");

            //vip2
            $twolayers_reward_4 = round($jackpot['vip2_twolayers_prize']*$twolayers_reward['twolayers_reward_4']/100,2);
            $res_twolayers_reward_4 =quarte_prize("twolayers_reward_4",$sales_reward['start_time'],$end_time,$jackpot['vip2_twolayers_prize'],$twolayers_reward_4,$twolayers_reward['twolayers_reward_4'],$jackpot['quarter'],$vip2_list,0,4,'二层外分红奖');

            //vip3
            $twolayers_reward_5 = round($jackpot['vip3_twolayers_prize']*$twolayers_reward['twolayers_reward_5']/100,2);
            $res_twolayers_reward_5 =quarte_prize("twolayers_reward_5",$sales_reward['start_time'],$end_time,$jackpot['vip3_twolayers_prize'],$twolayers_reward_5,$twolayers_reward['twolayers_reward_5'],$jackpot['quarter'],$vip3_list,0,4,'二层外分红奖');

            //vip4
            $twolayers_reward_6 = round($jackpot['vip4_twolayers_prize']*$twolayers_reward['twolayers_reward_6']/100,2);
            $res_twolayers_reward_6 =quarte_prize("twolayers_reward_5",$sales_reward['start_time'],$end_time,$jackpot['vip4_twolayers_prize'],$twolayers_reward_6,$twolayers_reward['twolayers_reward_6'],$jackpot['quarter'],$vip4_list,0,4,'二层外分红奖');


            //vip1及以上会员
            $where_u['level'] = array('egt',3);
            $user_list = M('users')->where($where_u)->field('user_id,level,user_money')->select();


            $order_list  = M('order')->alias('a')
                ->join('order_goods og','a.order_id=og.order_id')
                ->field('a.order_id,a.city,a.district,og.rec_id,og.sales_volume')
                ->where($where)
                ->select();


            //本季度所有市总销售额
            $city_sale_count = M('order')->alias('a')
                ->join('order_goods og','a.order_id=og.order_id')
                ->field('a.order_id,a.city,a.district,og.rec_id,sum(og.sales_volume) as sales_volume')
                ->where($where)
                ->group('a.city')
                ->select();

            //本季度所有市总销售额
            $district_sale_count = M('order')->alias('a')
                ->join('order_goods og','a.order_id=og.order_id')
                ->field('a.order_id,a.city,a.district,og.rec_id,sum(og.sales_volume) as sales_volume')
                ->where($where)
                ->group('a.district')
                ->select();


            //市代理列表
            $where_city['a.level']=6;
            $agency_city = M('users')->alias('a')
                ->join('agency_area ag','a.user_id=ag.user_id')
                ->where($where_city)
                ->column('city_id,a.user_id');


            //区代理列表
            $where_district['a.level']=5;
            $agency_district = M('users')->alias('a')
                ->join('agency_area ag','a.user_id=ag.user_id')
                ->where($where_district)
                ->column('area_id,a.user_id');

            $region_city_rate = tpCache('distribut.region_city_rate');
            $region_district_rate = tpCache('distribut.region_district_rate');


            //记录，分配区域保护奖(市)
            foreach($city_sale_count as $c_val){
                $res_qurter_region = quarter_region($agency_city[$c_val['city']],1,$c_val['city'],$jackpot['start_time'],$end_time,$c_val['sales_volume'],$region_city_rate,$jackpot['quarter'],0);
            }

            //记录，分配区域保护奖(区)
            foreach($city_sale_count as $d_val){
                $res_qurter_region = quarter_region($agency_district[$d_val['district']],2,$d_val['district'],$jackpot['start_time'],$end_time,$d_val['sales_volume'],$region_city_rate,$jackpot['quarter'],0);
            }


            $res_jsckpot =M('jackpot')->where(array('id'=>1))->update(array('start_time'=>$end_time,'vip2_twolayers_prize'=>0,'vip3_twolayers_prize'=>0,'vip4_twolayers_prize'=>0,'quarter'=>$jackpot['quarter']+1));

            //改变可分奖的订单为已分奖
            $res_order =M('order')->alias('a')->where($where)->update(array('is_jackpot_prize'=>1));

            if($res_order){
                $res_jsckpot =M('jackpot')->where(array('id'=>1))->update(array('start_time'=>$end_time,'vip2_twolayers_prize'=>0,'vip3_twolayers_prize'=>0,'vip4_twolayers_prize'=>0,'quarter'=>$jackpot['quarter']+1));
            }

            return array('status'=>1,'msg'=>"123");

        }


    }