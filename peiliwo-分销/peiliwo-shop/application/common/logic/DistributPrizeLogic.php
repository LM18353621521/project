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
    private $distribut_price = 0;//分佣金额
    private $contact_order = '';//订单信息
    //protected $log_name = ROOT_PATH."runtime/log/distribut.log";
    private   $logObj;

    protected  $sales_prize_user= array();

    public function __construct()
    {
        parent::__construct();
        $this->logObj = new Log();
    }

    //查找符合销售奖的人
    public function get_sales_prize_user($user_id,$level){

        //$level为空表示已经找到全部
        if(empty($level)){
            return $this->sales_prize_user;
        }

        $user =  Db::name('users')->field('user_id,first_leader,second_leader,third_leader,level')->where(['user_id' => $user_id])->find();

        $user_distribut = Db::name('users')->field('user_id,first_leader,second_leader,third_leader,level')->where(['user_id' => $user['first_leader']])->find();

        //已查找了所有的上级
        if(empty($user_distribut)){

            $r_sales_prize_user = $this->sales_prize_user;

            return $r_sales_prize_user;

        }

        if(in_array($user_distribut['level'],$level)){
            $index = array_search($user_distribut['level'],$level);
            unset($level[$index]);
            array_push($this->sales_prize_user,$user_distribut['level']);
        }

         return $this->get_sales_prize_user($user_distribut['user_id'],$level);

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
            $this->$val();
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
     * 推荐奖
     * */
    private function recommended_prize(){

    }



    /**
     * 普通产品分销奖
     * @return $order 订单
     */
    public function general_prize($order){

        $order_info = Order::with('OrderGoods.goods')->where('order_id',$order['order_id'])->find();

       // $goods_list = $order_info->order_goods;

        $this->contact_order = $order_info;

        $user = Users::get($order['user_id']);

        Db::startTrans();

        try{
            //生成分佣记录
            $this->userDistributDo($user['first_leader'],1,$order);
            $this->userDistributDo($user['second_leader'],2,$order);
            Db::commit();
        }catch(\Exception $e)
        {
            Db::rollback();
        }
    }

    /**
     * 推荐奖分佣获取
     * @return $order 订单
     */
    public function Referral_bonus($order){
        $order_info = order::with('OrderGoods.goods')->where('order_id',$order['order_id'])->find();

        $this->contact_order = $order_info;

        //查找订单产品
        $list_where = array(
            'order_id'    => $order['order_id']
        );
        $goods_data = M('order_goods')->field('identity_id')->where($list_where)->find();


        $user = Users::get($order['user_id']);

        Db::startTrans();

        try{
            //生成推荐分佣记录
            $this->userDistributDo($user['first_leader'],1,$order,$goods_data['identity_id']);
            $this->userDistributDo($user['second_leader'],2,$order,$goods_data['identity_id']);

            Db::commit();
        }catch(\Exception $e)
        {
            Db::rollback();
        }

    }

    /**
     *
     */
    public function give_vip_prize($order){
        $order_info = order::with('OrderGoods.goods')->where('order_id',$order['order_id'])->find();
        $this->contact_order = $order_info;
        $user = Users::get($order['user_id']);
        Db::startTrans();
        try{
            //生成赠送名额分佣记录  5-赠送名额分佣
            $this->userDistributDo($order_info['recommend_user_id'],5,$order,'');
            Db::commit();
        }catch(\Exception $e)
        {
            Db::rollback();
        }
    }

    /**
     * 生成分佣记录
     * @param int $user_id 获得分佣记录者id
     * @param int $level 被分佣者等级
     * @param int $order 被分佣者等级
     * @return $order 订单
     */
    private function userDistributDo($user_id,$level,$order,$identity= '')
    {
        //根据每一层的用户做处理
        $user_info = Users::get($user_id);

        //购买者
        $buy_user = M('users')->where(array('user_id'=>$order['user_id']))->find();

        if(empty($user_info)) return ;

        //拼接获取tp_distribut_system 的配置金额

        if($level==5){
            $price=$order['goods_price'];
            $data['type']= 2;
            $can_distribution=1;
        }else{
            $can_distribution = tpCache('distribut.can_distribution');
            if(empty($identity)){
                $price = $this->Distribution_logic($level,$user_info,$buy_user);
                $data['type'] = 0;
            }else{
                $price = $this->Recommended_logic($identity,$level,$user_info);
                $data['type'] = 1;
            }
        }


        if(empty($price))return;//没有配置金额

        $buy_user_info = Users::get($order['user_id']);

        //处理分成记录
        $data['maid_user'] = $user_info['nickname'];
        $data['user_id'] = $user_id;
        $data['buy_user_id'] = $order['user_id'];
        $data['nickname'] = $buy_user_info->nickname;
        $data['order_sn'] = $order['order_sn'];
        $data['order_id'] = $order['order_id'];
        $data['goods_price'] = $order['goods_price'];
        $data['money'] = $price;
        $data['level'] = $user_info['level'];
        $data['create_time'] = time();
        $data['status'] = $this->contact_order->pay_status;
        $data['can_distribution'] = $can_distribution;
        $data['pay_time'] = time();

        $log = db('rebate_log')->insert($data);

        if(!$log)
            throw new \think\Exception('处理分销记录出错');
    }

    /**
     * 分销配置金额获取逻辑
     * @param $level   获佣用户等级
     * @param $disbution_user  获佣用户
     * @param $buy_user   购买用户
     * @return array
     */
    private function Distribution_logic($level,$disbution_user,$buy_user){

        //分销等级金额获取
        $floor = $level == 1 ? 'first' : 'second';
        if($disbution_user['level'] == 2){
            $price_cache_name = "distribution_" . $floor . "_".$disbution_user['level']."_0";
        }else{
            $price_cache_name = "distribution_" . $floor . "_".$disbution_user['level'].'_'.$buy_user['level'];
        }

        return distributCache("distribution.".$price_cache_name);
    }

    /**
     * 推荐配置金额获取
     * @param $identity   购买系列
     * @param $level  获佣等级
     * @param $disbution_user   获佣用户
     * @return array|bool
     */
    private function Recommended_logic($identity,$level,$disbution_user){

        $Package_level = M('user_level')->field('level_id')->where(array('identity_id' => $identity))->find();
        $floor = $level == 1 ? 'first' : 'second';

        //分销等级金额获取
        $price_cache_name_switch = 'recommend_' . $floor . '_open_' . $disbution_user['level'] . '_' . $Package_level['level_id'];
        $is_switch = distributCache('recommend.' . $price_cache_name_switch);

        if(empty($is_switch)) return false;//配置开关

        $price_cache_name = "recommend_" . $floor . "_" . $disbution_user['level'] . '_' . $Package_level['level_id'];
        return distributCache("recommend.".$price_cache_name);
    }

    /*
   * 二层外分红奖奖池
   * @param $goods
   * */
    public function  twolayers_reward($order){
        //寻找上级

        if($order['is_jackpot']==1){return false;}

        $level = array(4,5,6);
        $user = M('users')->where(array('user_id'=>$order['user_id']))->find();
        $sales_prize_user = $this->get_sales_prize_user($user['second_leader'],$level);//递归调用判断

        if(empty($sales_prize_user))return false;//无二层外v2-v4直推者

        $is_identity = M('order_goods')->where(array('order_id'=>$order['order_id'],'identity_id'=>array('gt',0)))->find();
        $twolayers_where = array();

        //判断商品类型{普通 or 身份}
        if($is_identity){
            foreach($sales_prize_user as $key=>$val){
                $val = trim($val)-2;
                $twolayers_name = 'vip' . $val .'_twolayers_prize';
                $twolayers_where[$twolayers_name] = array('exp',$twolayers_name . '+' . $is_identity['sales_volume']);
            }
        }else{
            foreach($sales_prize_user as $key=>$val){
                $val = trim($val)-2;
                $twolayers_name = 'vip' . $val .'_twolayers_prize';
                $twolayers_where[$twolayers_name] = array('exp',$twolayers_name . '+' . $order['goods_price']);
            }
        }
        M('order')->where(array('order_id'=>$order['order_id']))->update(array('is_jackpot'=>1));
       $res =  M('jackpot')->where(array('id'=>1))->save($twolayers_where);
    }

    /*
     * 区域奖
     * @param $goods
     * */
    public function region_prize(){

    }



    /*
    * 递归查询符合条件
    * */
    public function recursive($id, $level_arr, $prize_info){
        if (!$id && $level_arr && !$prize_info) {
            return ['code' =>-2, 'msg' => '请传参数'];
        }
        $userInfo = Db::name('users')->field('user_id,first_leader,second_leader,third_leader,level')->where(['user_id' => $id])->find();
        if (!$userInfo)  return false;
        if (in_array($userInfo['level'],$level_arr)){
            $config = distributCache($prize_info.'-'.$userInfo['level']);
            if ($config[$prize_info.'_'.'switch'])
                return $userInfo;
            else
                return $this->recursive($userInfo['second_leader'],$level_arr,$prize_info);
        } else {
            $path_arr = [$userInfo['first_leader'],$userInfo['second_leader'], $userInfo['third_leader']];
            $path_info = Db::name('users')
                ->field('user_id,first_leader,second_leader,third_leader,level')
                ->where(['user_id' =>['in' , $path_arr]])
                ->select();
            //  出现无符合人员 先确认对应层级的团队奖是否开启
            if (!$path_info) return false;
            foreach ($path_info as $key => $value) {
                if (in_array($value['level'],$level_arr)){
                    // 判断开关是否开启
                    $config = distributCache($prize_info.'-'.$value['level']);
                    if ($config[$prize_info.'_'.'switch']) {
                        return $value;
                    }
                }
            }
            $len = count($path_info) -1;
            return $this->recursive($path_info[$len]['first_leader'],$level_arr, $prize_info);
        }
    }
}